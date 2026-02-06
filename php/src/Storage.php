<?php

namespace Spies;

class Storage
{
    private static string $dataDir = __DIR__ . '/../data';

    public static function init(): void
    {
        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777, true);
        }
    }

    public static function saveRoom(Room $room): void
    {
        self::init();
        $file = self::$dataDir . '/room_' . $room->code . '.json';
        file_put_contents($file, json_encode($room->toArray()), LOCK_EX);
    }

    public static function loadRoom(string $code): ?Room
    {
        self::init();
        $file = self::$dataDir . '/room_' . strtoupper($code) . '.json';
        if (!file_exists($file)) {
            return null;
        }
        $data = json_decode(file_get_contents($file), true);
        if (!$data) {
            return null;
        }
        return Room::fromArray($data);
    }

    public static function deleteRoom(string $code): void
    {
        $file = self::$dataDir . '/room_' . strtoupper($code) . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public static function roomExists(string $code): bool
    {
        $file = self::$dataDir . '/room_' . strtoupper($code) . '.json';
        return file_exists($file);
    }

    public static function generateRoomCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (self::roomExists($code));
        return $code;
    }

    public static function cleanOldRooms(int $maxAgeSeconds = 3600): void
    {
        self::init();
        $files = glob(self::$dataDir . '/room_*.json');
        $now = time();
        foreach ($files as $file) {
            if ($now - filemtime($file) > $maxAgeSeconds) {
                unlink($file);
            }
        }
    }
}
