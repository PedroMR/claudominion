<?php

namespace Spies;

class Cards
{
    public const DEFINITIONS = [
        // Treasure Cards
        'Copper' => [
            'name' => 'Copper',
            'cost' => 0,
            'type' => 'treasure',
            'effect' => ['coins' => 1],
            'description' => '+1 Coin',
        ],
        'Silver' => [
            'name' => 'Silver',
            'cost' => 3,
            'type' => 'treasure',
            'effect' => ['coins' => 2],
            'description' => '+2 Coins',
        ],
        'Gold' => [
            'name' => 'Gold',
            'cost' => 6,
            'type' => 'treasure',
            'effect' => ['coins' => 3],
            'description' => '+3 Coins',
        ],

        // Victory Cards
        'Estate' => [
            'name' => 'Estate',
            'cost' => 2,
            'type' => 'victory',
            'effect' => ['vp' => 1],
            'description' => '1 VP',
        ],
        'Duchy' => [
            'name' => 'Duchy',
            'cost' => 5,
            'type' => 'victory',
            'effect' => ['vp' => 3],
            'description' => '3 VP',
        ],
        'Province' => [
            'name' => 'Province',
            'cost' => 8,
            'type' => 'victory',
            'effect' => ['vp' => 6],
            'description' => '6 VP',
        ],

        // Action Cards
        'Village' => [
            'name' => 'Village',
            'cost' => 3,
            'type' => 'action',
            'effect' => ['cards' => 1, 'actions' => 2],
            'description' => '+1 Card, +2 Actions',
        ],
        'Smithy' => [
            'name' => 'Smithy',
            'cost' => 4,
            'type' => 'action',
            'effect' => ['cards' => 3],
            'description' => '+3 Cards',
        ],
        'Market' => [
            'name' => 'Market',
            'cost' => 5,
            'type' => 'action',
            'effect' => ['cards' => 1, 'actions' => 1, 'buys' => 1, 'coins' => 1],
            'description' => '+1 Card, +1 Action, +1 Buy, +1 Coin',
        ],
        'Spy' => [
            'name' => 'Spy',
            'cost' => 4,
            'type' => 'action',
            'effect' => ['cards' => 1, 'actions' => 1, 'special' => 'spy'],
            'description' => '+1 Card, +1 Action; Each player reveals top card, you choose discard or keep',
        ],
    ];

    public static function getDefinition(string $name): ?array
    {
        return self::DEFINITIONS[$name] ?? null;
    }

    public static function getAllNames(): array
    {
        return array_keys(self::DEFINITIONS);
    }
}
