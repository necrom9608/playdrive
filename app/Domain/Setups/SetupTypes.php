<?php

namespace App\Domain\Setups;

/** Soorten speelplekken. Sleutel → label. */
class SetupTypes
{
    public const TYPES = [
        'arcade'  => 'Arcadekast',
        'pc'      => 'PC',
        'console' => 'Console / couch',
        'vr'      => 'VR',
        'pinball' => 'Flipperkast',
        'other'   => 'Andere',
    ];

    public const STATUSES = [
        'active'      => 'Actief',
        'maintenance' => 'In onderhoud',
        'archived'    => 'Gearchiveerd',
    ];

    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function label(?string $type): string
    {
        return self::TYPES[$type] ?? 'Andere';
    }

    public static function all(): array
    {
        return collect(self::TYPES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    public static function statuses(): array
    {
        return collect(self::STATUSES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }
}
