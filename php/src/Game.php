<?php

namespace Spies;

class Game
{
    private static int $cardIdCounter = 0;

    public string $id;
    public string $phase = 'action';
    public int $currentPlayer = 0;
    public array $players = [];
    public array $supply = [];
    public array $trash = [];
    public array $turnState;
    public ?array $spyPending = null;
    public ?string $winner = null;
    public array $log = [];

    public function __construct(string $id, array $playerData)
    {
        $this->id = $id;
        $this->turnState = $this->createInitialTurnState();
        $this->supply = $this->createInitialSupply(count($playerData));

        foreach ($playerData as $p) {
            $this->players[] = $this->createPlayer($p['id'], $p['name']);
        }

        $this->log[] = "Game started with " . count($playerData) . " players";
        $this->maybeSkipActionPhase();
    }

    private static function createCard(string $name): array
    {
        return [
            'id' => $name . '-' . (++self::$cardIdCounter),
            'name' => $name,
        ];
    }

    private static function shuffle(array $array): array
    {
        $result = $array;
        for ($i = count($result) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$result[$i], $result[$j]] = [$result[$j], $result[$i]];
        }
        return $result;
    }

    private function createInitialDeck(): array
    {
        $deck = [];
        for ($i = 0; $i < 7; $i++) {
            $deck[] = self::createCard('Copper');
        }
        for ($i = 0; $i < 3; $i++) {
            $deck[] = self::createCard('Estate');
        }
        return self::shuffle($deck);
    }

    private function createPlayer(string $id, string $name): array
    {
        $deck = $this->createInitialDeck();
        return [
            'id' => $id,
            'name' => $name,
            'deck' => array_slice($deck, 5),
            'hand' => array_slice($deck, 0, 5),
            'discard' => [],
            'inPlay' => [],
            'connected' => true,
        ];
    }

    private function createInitialSupply(int $playerCount): array
    {
        $victoryCards = $playerCount === 2 ? 8 : 12;
        return [
            'Copper' => 60 - ($playerCount * 7),
            'Silver' => 40,
            'Gold' => 30,
            'Estate' => $victoryCards,
            'Duchy' => $victoryCards,
            'Province' => $victoryCards,
            'Village' => 10,
            'Smithy' => 10,
            'Market' => 10,
            'Spy' => 10,
        ];
    }

    private function createInitialTurnState(): array
    {
        return [
            'actions' => 1,
            'buys' => 1,
            'coins' => 0,
        ];
    }

    private function drawCards(int $playerIndex, int $count): array
    {
        $player = &$this->players[$playerIndex];
        $drawn = [];

        for ($i = 0; $i < $count; $i++) {
            if (empty($player['deck'])) {
                if (empty($player['discard'])) {
                    break;
                }
                $player['deck'] = self::shuffle($player['discard']);
                $player['discard'] = [];
            }
            $card = array_pop($player['deck']);
            if ($card) {
                $drawn[] = $card;
                $player['hand'][] = $card;
            }
        }
        return $drawn;
    }

    private function hasPlayableActions(int $playerIndex): bool
    {
        if ($this->turnState['actions'] <= 0) {
            return false;
        }
        $player = $this->players[$playerIndex];
        foreach ($player['hand'] as $card) {
            $def = Cards::getDefinition($card['name']);
            if ($def && $def['type'] === 'action') {
                return true;
            }
        }
        return false;
    }

    private function maybeSkipActionPhase(): void
    {
        if ($this->phase !== 'action' || $this->spyPending !== null) {
            return;
        }
        if (!$this->hasPlayableActions($this->currentPlayer)) {
            $this->phase = 'buy';
            $this->playAllTreasures($this->currentPlayer);
        }
    }

    public function canPlayCard(int $playerIndex, int $cardIndex): bool
    {
        if ($this->phase !== 'action') return false;
        if ($this->currentPlayer !== $playerIndex) return false;
        if ($this->spyPending !== null) return false;

        $player = $this->players[$playerIndex];
        if ($cardIndex < 0 || $cardIndex >= count($player['hand'])) return false;

        $card = $player['hand'][$cardIndex];
        $def = Cards::getDefinition($card['name']);
        if (!$def || $def['type'] !== 'action') return false;
        if ($this->turnState['actions'] <= 0) return false;

        return true;
    }

    public function playCard(int $playerIndex, int $cardIndex): bool
    {
        if (!$this->canPlayCard($playerIndex, $cardIndex)) {
            return false;
        }

        $player = &$this->players[$playerIndex];
        $card = array_splice($player['hand'], $cardIndex, 1)[0];
        $player['inPlay'][] = $card;

        $def = Cards::getDefinition($card['name']);
        $this->turnState['actions']--;

        if (isset($def['effect']['actions'])) {
            $this->turnState['actions'] += $def['effect']['actions'];
        }
        if (isset($def['effect']['buys'])) {
            $this->turnState['buys'] += $def['effect']['buys'];
        }
        if (isset($def['effect']['coins'])) {
            $this->turnState['coins'] += $def['effect']['coins'];
        }
        if (isset($def['effect']['cards'])) {
            $this->drawCards($playerIndex, $def['effect']['cards']);
        }

        $this->log[] = "{$player['name']} played {$card['name']}";

        if (isset($def['effect']['special']) && $def['effect']['special'] === 'spy') {
            $this->initiateSpy($playerIndex);
        }

        $this->maybeSkipActionPhase();
        return true;
    }

    private function initiateSpy(int $playerIndex): void
    {
        $allPlayerIds = [];
        $numPlayers = count($this->players);
        for ($i = 0; $i < $numPlayers; $i++) {
            $idx = ($playerIndex + $i) % $numPlayers;
            $allPlayerIds[] = $this->players[$idx]['id'];
        }

        $this->revealNextSpy($allPlayerIds);
    }

    private function revealNextSpy(array $remainingPlayerIds): void
    {
        foreach ($remainingPlayerIds as $i => $playerId) {
            $playerIndex = $this->findPlayerIndex($playerId);
            $player = &$this->players[$playerIndex];

            if (empty($player['deck']) && !empty($player['discard'])) {
                $player['deck'] = self::shuffle($player['discard']);
                $player['discard'] = [];
            }

            if (!empty($player['deck'])) {
                $revealedCard = $player['deck'][count($player['deck']) - 1];
                $this->spyPending = [
                    'targetPlayerId' => $playerId,
                    'revealedCard' => $revealedCard,
                    'remainingPlayerIds' => array_slice($remainingPlayerIds, $i + 1),
                ];
                $this->log[] = "{$player['name']} reveals {$revealedCard['name']}";
                return;
            }
        }
    }

    public function handleSpyChoice(int $playerIndex, bool $discard): bool
    {
        if ($this->spyPending === null) return false;
        if ($this->currentPlayer !== $playerIndex) return false;

        $targetIndex = $this->findPlayerIndex($this->spyPending['targetPlayerId']);
        $target = &$this->players[$targetIndex];
        $currentPlayer = $this->players[$this->currentPlayer];
        $remainingPlayerIds = $this->spyPending['remainingPlayerIds'];

        if ($discard) {
            $card = array_pop($target['deck']);
            $target['discard'][] = $card;
            $this->log[] = "{$currentPlayer['name']} chose to discard {$target['name']}'s {$card['name']}";
        } else {
            $this->log[] = "{$currentPlayer['name']} chose to keep {$target['name']}'s card on top";
        }

        $this->spyPending = null;
        $this->revealNextSpy($remainingPlayerIds);
        $this->maybeSkipActionPhase();

        return true;
    }

    private function playAllTreasures(int $playerIndex): void
    {
        $player = &$this->players[$playerIndex];
        $treasureIndices = [];

        foreach ($player['hand'] as $i => $card) {
            $def = Cards::getDefinition($card['name']);
            if ($def && $def['type'] === 'treasure') {
                $treasureIndices[] = $i;
            }
        }

        $played = 0;
        foreach (array_reverse($treasureIndices) as $i) {
            $card = array_splice($player['hand'], $i, 1)[0];
            $player['inPlay'][] = $card;
            $def = Cards::getDefinition($card['name']);
            if (isset($def['effect']['coins'])) {
                $this->turnState['coins'] += $def['effect']['coins'];
            }
            $played++;
        }

        if ($played > 0) {
            $this->log[] = "{$player['name']} played {$played} treasure(s)";
        }
    }

    public function canBuyCard(int $playerIndex, string $cardName): bool
    {
        if ($this->phase !== 'buy') return false;
        if ($this->currentPlayer !== $playerIndex) return false;
        if ($this->turnState['buys'] <= 0) return false;

        $def = Cards::getDefinition($cardName);
        if (!$def) return false;

        if (!isset($this->supply[$cardName]) || $this->supply[$cardName] <= 0) return false;
        if ($this->turnState['coins'] < $def['cost']) return false;

        return true;
    }

    public function buyCard(int $playerIndex, string $cardName): bool
    {
        if (!$this->canBuyCard($playerIndex, $cardName)) {
            return false;
        }

        $player = &$this->players[$playerIndex];
        $def = Cards::getDefinition($cardName);

        $this->supply[$cardName]--;
        $player['discard'][] = self::createCard($cardName);
        $this->turnState['coins'] -= $def['cost'];
        $this->turnState['buys']--;

        $this->log[] = "{$player['name']} bought {$cardName}";
        return true;
    }

    public function endActionPhase(int $playerIndex): bool
    {
        if ($this->phase !== 'action') return false;
        if ($this->currentPlayer !== $playerIndex) return false;
        if ($this->spyPending !== null) return false;

        $this->phase = 'buy';
        $this->playAllTreasures($playerIndex);
        return true;
    }

    public function endBuyPhase(int $playerIndex): bool
    {
        if ($this->phase !== 'buy') return false;
        if ($this->currentPlayer !== $playerIndex) return false;

        $this->cleanup($playerIndex);
        return true;
    }

    private function cleanup(int $playerIndex): void
    {
        $player = &$this->players[$playerIndex];

        $player['discard'] = array_merge($player['discard'], $player['hand'], $player['inPlay']);
        $player['hand'] = [];
        $player['inPlay'] = [];

        $this->drawCards($playerIndex, 5);

        if ($this->isGameOver()) {
            $this->phase = 'ended';
            $scores = $this->calculateScores();
            $maxScore = max(array_column($scores, 'score'));
            $winners = array_filter($scores, fn($s) => $s['score'] === $maxScore);
            $this->winner = implode(', ', array_column($winners, 'name'));
            $this->log[] = "Game over! Winner: {$this->winner}";
            foreach ($scores as $s) {
                $this->log[] = "{$s['name']}: {$s['score']} VP";
            }
            return;
        }

        $this->currentPlayer = ($playerIndex + 1) % count($this->players);
        $this->phase = 'action';
        $this->turnState = $this->createInitialTurnState();

        $this->log[] = "{$this->players[$this->currentPlayer]['name']}'s turn";
        $this->maybeSkipActionPhase();
    }

    public function isGameOver(): bool
    {
        if ($this->supply['Province'] === 0) return true;
        $emptyPiles = count(array_filter($this->supply, fn($c) => $c === 0));
        return $emptyPiles >= 3;
    }

    public function calculateScores(): array
    {
        $scores = [];
        foreach ($this->players as $player) {
            $allCards = array_merge($player['deck'], $player['hand'], $player['discard'], $player['inPlay']);
            $score = 0;
            foreach ($allCards as $card) {
                $def = Cards::getDefinition($card['name']);
                if ($def && isset($def['effect']['vp'])) {
                    $score += $def['effect']['vp'];
                }
            }
            $scores[] = ['name' => $player['name'], 'score' => $score];
        }
        return $scores;
    }

    public function findPlayerIndex(string $playerId): int
    {
        foreach ($this->players as $i => $player) {
            if ($player['id'] === $playerId) {
                return $i;
            }
        }
        return -1;
    }

    public function setPlayerConnected(string $playerId, bool $connected): void
    {
        $index = $this->findPlayerIndex($playerId);
        if ($index >= 0) {
            $this->players[$index]['connected'] = $connected;
        }
    }

    public function toClientState(string $forPlayerId): array
    {
        $players = [];
        foreach ($this->players as $player) {
            $clientPlayer = [
                'id' => $player['id'],
                'name' => $player['name'],
                'handCount' => count($player['hand']),
                'deckCount' => count($player['deck']),
                'discardCount' => count($player['discard']),
                'inPlay' => $player['inPlay'],
                'connected' => $player['connected'],
            ];
            if ($player['id'] === $forPlayerId) {
                $clientPlayer['hand'] = $player['hand'];
            }
            $players[] = $clientPlayer;
        }

        return [
            'id' => $this->id,
            'phase' => $this->phase,
            'currentPlayer' => $this->currentPlayer,
            'players' => $players,
            'supply' => $this->supply,
            'trashCount' => count($this->trash),
            'turnState' => $this->turnState,
            'spyPending' => $this->spyPending,
            'winner' => $this->winner,
            'log' => array_slice($this->log, -20),
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'phase' => $this->phase,
            'currentPlayer' => $this->currentPlayer,
            'players' => $this->players,
            'supply' => $this->supply,
            'trash' => $this->trash,
            'turnState' => $this->turnState,
            'spyPending' => $this->spyPending,
            'winner' => $this->winner,
            'log' => $this->log,
            'cardIdCounter' => self::$cardIdCounter,
        ];
    }

    public static function fromArray(array $data): Game
    {
        // Use reflection to create instance without calling constructor
        $reflection = new \ReflectionClass(self::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $instance->id = $data['id'];
        $instance->phase = $data['phase'];
        $instance->currentPlayer = $data['currentPlayer'];
        $instance->players = $data['players'];
        $instance->supply = $data['supply'];
        $instance->trash = $data['trash'];
        $instance->turnState = $data['turnState'];
        $instance->spyPending = $data['spyPending'];
        $instance->winner = $data['winner'];
        $instance->log = $data['log'];

        if (isset($data['cardIdCounter'])) {
            self::$cardIdCounter = $data['cardIdCounter'];
        }

        return $instance;
    }
}
