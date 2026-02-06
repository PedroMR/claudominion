<?php

namespace Spies;

class Room
{
    public string $code;
    public array $players = [];
    public bool $gameStarted = false;
    public ?Game $game = null;
    public int $lastUpdate;
    public int $version = 0;

    public function __construct(string $code)
    {
        $this->code = $code;
        $this->lastUpdate = time();
    }

    public function addPlayer(string $id, string $name): bool
    {
        if ($this->gameStarted || count($this->players) >= 4) {
            return false;
        }
        // Check if player already exists
        foreach ($this->players as $p) {
            if ($p['id'] === $id) {
                return true; // Already joined
            }
        }
        $this->players[] = ['id' => $id, 'name' => $name];
        $this->touch();
        return true;
    }

    public function removePlayer(string $id): bool
    {
        foreach ($this->players as $i => $player) {
            if ($player['id'] === $id) {
                array_splice($this->players, $i, 1);
                $this->touch();
                return true;
            }
        }
        return false;
    }

    public function startGame(): bool
    {
        if ($this->gameStarted || count($this->players) < 2) {
            return false;
        }
        $this->gameStarted = true;
        $this->game = new Game($this->code, $this->players);
        $this->touch();
        return true;
    }

    public function touch(): void
    {
        $this->lastUpdate = time();
        $this->version++;
    }

    public function getPlayerIds(): array
    {
        return array_column($this->players, 'id');
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'players' => $this->players,
            'gameStarted' => $this->gameStarted,
            'game' => $this->game ? $this->game->toArray() : null,
            'lastUpdate' => $this->lastUpdate,
            'version' => $this->version,
        ];
    }

    public static function fromArray(array $data): Room
    {
        $room = new Room($data['code']);
        $room->players = $data['players'];
        $room->gameStarted = $data['gameStarted'];
        $room->lastUpdate = $data['lastUpdate'] ?? time();
        $room->version = $data['version'] ?? 0;
        if ($data['game']) {
            $room->game = Game::fromArray($data['game']);
        }
        return $room;
    }
}
