<?php

namespace App\Domain\Display;

use App\Models\DisplaySetting;
use App\Models\DisplayVideo;
use App\Models\Tenant;

/**
 * Skins voor het klantenscherm en wat het scherm ervan nodig heeft.
 *
 *   standaard  het bestaande PlayDrive-scherm
 *   gameinn    zoals game-inn.be: verticale filmpjes met CRT-effect, embleem met de acht zones,
 *              oranje lopende band; bij een bestelling wordt het filmpje een strook bovenaan.
 */
class DisplaySkin
{
    public const SKINS = [
        'standaard' => 'PlayDrive (standaard)',
        'gameinn'   => 'Game-INN',
    ];

    public const CRT = ['uit', 'zacht', 'sterk'];

    private const DEFAULTS = [
        'standaard' => [
            'title_line_1'   => null,
            'title_line_2'   => null,
            'info_text'      => null,
            'standby_ticker' => [],
            'order_ticker'   => [],
        ],
        'gameinn' => [
            'title_line_1'   => 'Eén ticket.',
            'title_line_2'   => 'Alles spelen.',
            'info_text'      => 'Waregem · *vlak aan het station*',
            'standby_ticker' => ['Geen muntjes', 'Geen kaartjes', 'Alle toestellen inbegrepen', '100+ arcadekasten', 'Gratis parking', 'Begeleiders gratis'],
            'order_ticker'   => ['Bedankt en veel speelplezier', 'Word lid: 1 jaar onbeperkt'],
        ],
    ];

    public static function settingsFor(int $tenantId): DisplaySetting
    {
        return DisplaySetting::query()->firstOrNew(['tenant_id' => $tenantId], [
            'skin'       => 'standaard',
            'show_hours' => true,
            'crt'        => 'zacht',
        ]);
    }

    /** Instellingen met lege velden aangevuld met de standaard van de skin. */
    public static function resolved(DisplaySetting $s): array
    {
        $skin = array_key_exists($s->skin, self::SKINS) ? $s->skin : 'standaard';
        $d = self::DEFAULTS[$skin];

        $list = fn ($v, $fallback) => is_array($v) && count(array_filter($v, 'filled')) ? array_values(array_filter($v, 'filled')) : $fallback;

        return [
            'skin'           => $skin,
            'title_line_1'   => filled($s->title_line_1) ? $s->title_line_1 : $d['title_line_1'],
            'title_line_2'   => filled($s->title_line_2) ? $s->title_line_2 : $d['title_line_2'],
            'info_text'      => filled($s->info_text) ? $s->info_text : $d['info_text'],
            'standby_ticker' => $list($s->standby_ticker, $d['standby_ticker']),
            'order_ticker'   => $list($s->order_ticker, $d['order_ticker']),
            'show_hours'     => (bool) ($s->show_hours ?? true),
            'crt'            => in_array($s->crt, self::CRT, true) ? $s->crt : 'zacht',
        ];
    }

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /** Alles wat het klantenscherm ophaalt (GET /api/display/skin). */
    public static function payload(Tenant $tenant): array
    {
        $settings = self::resolved(self::settingsFor($tenant->id));

        $videos = DisplayVideo::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('status', DisplayVideo::STATUS_READY)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->filter(fn (DisplayVideo $v) => $v->isPlayable())
            ->map(fn (DisplayVideo $v) => [
                'id'       => $v->id,
                'title'    => $v->title,
                'zone'     => $v->zone,
                'url'      => $v->publicUrl(),
                'duration' => $v->duration_seconds,
            ])->values();

        return [
            'skin'        => $settings['skin'],
            'settings'    => $settings,
            'videos'      => $videos,
            'hours'       => $settings['show_hours'] ? (new OpeningHoursToday($tenant))->summary() : null,
            'server_time' => now()->toIso8601String(),
            'assets'      => [
                'logo'   => asset('images/skins/gameinn/logo.webp'),
                'emblem' => asset('images/skins/gameinn/emblem.webp'),
            ],
        ];
    }
}
