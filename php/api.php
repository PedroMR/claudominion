<?php

require_once __DIR__ . '/src/Cards.php';
require_once __DIR__ . '/src/Game.php';
require_once __DIR__ . '/src/Room.php';
require_once __DIR__ . '/src/Storage.php';

use Spies\Storage;
use Spies\Room;
use Spies\Cards;

session_start();
header('Content-Type: application/json');

// Clean old rooms occasionally
if (random_int(1, 100) === 1) {
    Storage::cleanOldRooms();
}

// Get player ID and room code from request (client-generated)
$requestPlayerId = $_POST['playerId'] ?? $_GET['playerId'] ?? null;
$requestRoomCode = $_POST['roomCode'] ?? $_GET['roomCode'] ?? null;

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ['success' => false, 'error' => 'Unknown action'];

try {
    switch ($action) {
        case 'create-room':
            $response = handleCreateRoom();
            break;

        case 'join-room':
            $response = handleJoinRoom($_POST['code'] ?? '', $_POST['playerName'] ?? '');
            break;

        case 'leave-room':
            $response = handleLeaveRoom();
            break;

        case 'start-game':
            $response = handleStartGame();
            break;

        case 'play-card':
            $response = handlePlayCard((int) ($_POST['cardIndex'] ?? -1));
            break;

        case 'buy-card':
            $response = handleBuyCard($_POST['cardName'] ?? '');
            break;

        case 'end-phase':
            $response = handleEndPhase();
            break;

        case 'spy-choice':
            $response = handleSpyChoice($_POST['discard'] === 'true' || $_POST['discard'] === '1');
            break;

        case 'poll':
            $response = handlePoll((int) ($_GET['version'] ?? 0));
            break;

        case 'get-state':
            $response = handleGetState();
            break;

        case 'get-cards':
            $response = ['success' => true, 'cards' => Cards::DEFINITIONS];
            break;
    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);

function getPlayerId(): string
{
    global $requestPlayerId;

    // Use client-provided player ID if available
    if ($requestPlayerId) {
        return $requestPlayerId;
    }

    // Fall back to session-based ID
    if (!isset($_SESSION['playerId'])) {
        $_SESSION['playerId'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['playerId'];
}

function handleCreateRoom(): array
{
    $code = Storage::generateRoomCode();
    $room = new Room($code);
    Storage::saveRoom($room);

    return ['success' => true, 'code' => $code];
}

function handleJoinRoom(string $code, string $playerName): array
{
    if (empty($code) || empty($playerName)) {
        return ['success' => false, 'error' => 'Code and name required'];
    }

    $room = Storage::loadRoom($code);
    if (!$room) {
        return ['success' => false, 'error' => 'Room not found'];
    }

    $playerId = getPlayerId();
    if (!$room->addPlayer($playerId, $playerName)) {
        return ['success' => false, 'error' => 'Room is full or game already started'];
    }

    Storage::saveRoom($room);

    return [
        'success' => true,
        'roomCode' => $room->code,
        'players' => $room->players,
        'playerId' => $playerId,
        'version' => $room->version,
    ];
}

function handleLeaveRoom(): array
{
    $room = getPlayerRoom();
    if (!$room) {
        return ['success' => false, 'error' => 'Not in a room'];
    }

    if (!$room->gameStarted) {
        $room->removePlayer(getPlayerId());
        if (empty($room->players)) {
            Storage::deleteRoom($room->code);
        } else {
            Storage::saveRoom($room);
        }
    }

    return ['success' => true];
}

function handleStartGame(): array
{
    $room = getPlayerRoom();
    if (!$room) {
        return ['success' => false, 'error' => 'Not in a room'];
    }

    if (!$room->startGame()) {
        return ['success' => false, 'error' => 'Cannot start game (need 2+ players)'];
    }

    Storage::saveRoom($room);

    return [
        'success' => true,
        'gameState' => $room->game->toClientState(getPlayerId()),
        'version' => $room->version,
    ];
}

function handlePlayCard(int $cardIndex): array
{
    $room = getPlayerRoom();
    if (!$room || !$room->game) {
        return ['success' => false, 'error' => 'Not in a game'];
    }

    $playerIndex = $room->game->findPlayerIndex(getPlayerId());
    if (!$room->game->playCard($playerIndex, $cardIndex)) {
        return ['success' => false, 'error' => 'Invalid action'];
    }

    $room->touch();
    Storage::saveRoom($room);

    return [
        'success' => true,
        'gameState' => $room->game->toClientState(getPlayerId()),
        'version' => $room->version,
    ];
}

function handleBuyCard(string $cardName): array
{
    $room = getPlayerRoom();
    if (!$room || !$room->game) {
        return ['success' => false, 'error' => 'Not in a game'];
    }

    $playerIndex = $room->game->findPlayerIndex(getPlayerId());
    if (!$room->game->buyCard($playerIndex, $cardName)) {
        return ['success' => false, 'error' => 'Invalid action'];
    }

    $room->touch();
    Storage::saveRoom($room);

    return [
        'success' => true,
        'gameState' => $room->game->toClientState(getPlayerId()),
        'version' => $room->version,
    ];
}

function handleEndPhase(): array
{
    $room = getPlayerRoom();
    if (!$room || !$room->game) {
        return ['success' => false, 'error' => 'Not in a game'];
    }

    $playerIndex = $room->game->findPlayerIndex(getPlayerId());

    $success = false;
    if ($room->game->phase === 'action') {
        $success = $room->game->endActionPhase($playerIndex);
    } elseif ($room->game->phase === 'buy') {
        $success = $room->game->endBuyPhase($playerIndex);
    }

    if (!$success) {
        return ['success' => false, 'error' => 'Invalid action'];
    }

    $room->touch();
    Storage::saveRoom($room);

    return [
        'success' => true,
        'gameState' => $room->game->toClientState(getPlayerId()),
        'version' => $room->version,
    ];
}

function handleSpyChoice(bool $discard): array
{
    $room = getPlayerRoom();
    if (!$room || !$room->game) {
        return ['success' => false, 'error' => 'Not in a game'];
    }

    $playerIndex = $room->game->findPlayerIndex(getPlayerId());
    if (!$room->game->handleSpyChoice($playerIndex, $discard)) {
        return ['success' => false, 'error' => 'Invalid action'];
    }

    $room->touch();
    Storage::saveRoom($room);

    return [
        'success' => true,
        'gameState' => $room->game->toClientState(getPlayerId()),
        'version' => $room->version,
    ];
}

function handlePoll(int $clientVersion): array
{
    $room = getPlayerRoom();
    if (!$room) {
        return ['success' => true, 'changed' => false, 'noRoom' => true];
    }

    if ($room->version <= $clientVersion) {
        return ['success' => true, 'changed' => false, 'version' => $room->version];
    }

    $result = [
        'success' => true,
        'changed' => true,
        'version' => $room->version,
        'players' => $room->players,
        'gameStarted' => $room->gameStarted,
    ];

    if ($room->game) {
        $result['gameState'] = $room->game->toClientState(getPlayerId());
    }

    return $result;
}

function handleGetState(): array
{
    $room = getPlayerRoom();
    if (!$room) {
        return ['success' => false, 'error' => 'Not in a room'];
    }

    $playerId = getPlayerId();

    // Verify player is actually in this room
    $inRoom = false;
    foreach ($room->players as $p) {
        if ($p['id'] === $playerId) {
            $inRoom = true;
            break;
        }
    }

    if (!$inRoom && $room->gameStarted) {
        // Check if player is in the game
        if ($room->game) {
            $inRoom = $room->game->findPlayerIndex($playerId) >= 0;
        }
    }

    if (!$inRoom) {
        return ['success' => false, 'error' => 'Not in this room'];
    }

    $result = [
        'success' => true,
        'roomCode' => $room->code,
        'players' => $room->players,
        'gameStarted' => $room->gameStarted,
        'playerId' => $playerId,
        'version' => $room->version,
    ];

    if ($room->game) {
        $result['gameState'] = $room->game->toClientState($playerId);
    }

    return $result;
}

function getPlayerRoom(): ?Room
{
    global $requestRoomCode;

    $roomCode = $requestRoomCode ?? $_SESSION['roomCode'] ?? null;
    if (!$roomCode) {
        return null;
    }
    return Storage::loadRoom($roomCode);
}
