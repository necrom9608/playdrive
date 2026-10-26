<?php

namespace App\Domain\Arcade;

/** Games die via Asphyxia binnenkomen: sleutel (zoals playdrive-sync hem stuurt) → weergave. */
class ArcadeGames
{
    public const GAMES = [
        'drs'       => ['name' => 'DANCERUSH STARDOM', 'short' => 'DANCERUSH', 'per_play' => true],
        'ddr'       => ['name' => 'DanceDanceRevolution', 'short' => 'DDR', 'per_play' => true],
        'popn'      => ['name' => "pop'n music", 'short' => "pop'n", 'per_play' => false],
        'sdvx'      => ['name' => 'SOUND VOLTEX', 'short' => 'SDVX', 'per_play' => false],
        'nostalgia' => ['name' => 'NOSTALGIA', 'short' => 'NOSTALGIA', 'per_play' => false],
    ];

    /** Games waarvan Asphyxia een speelteller per chart bijhoudt. */
    public const COUNTED = ['drs', 'popn', 'nostalgia'];

    public static function exists(string $game): bool
    {
        return isset(self::GAMES[$game]);
    }

    public static function name(string $game): string
    {
        return self::GAMES[$game]['name'] ?? strtoupper($game);
    }

    public static function short(string $game): string
    {
        return self::GAMES[$game]['short'] ?? strtoupper($game);
    }

    public static function all(): array
    {
        $out = [];
        foreach (self::GAMES as $key => $g) {
            $out[] = ['key' => $key, 'name' => $g['name'], 'short' => $g['short']];
        }

        return $out;
    }
}
