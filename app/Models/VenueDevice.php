<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Onbemand toestel in een venue (kioskscherm, Playscope-PC).
 *
 * Levenscyclus:
 *   1. Toestel vraagt een koppelcode aan (tenant_id = null, pairing_code + poll_secret)
 *   2. Admin claimt de code in de backoffice (tenant_id, naam, setup)
 *   3. Toestel pollt en krijgt één keer zijn token; daarna Bearer-auth
 *   4. Verwijderen in de backoffice = token ongeldig, toestel valt terug naar koppelen
 */
class VenueDevice extends Model
{
    public const TYPES = [
        'kiosk'     => 'Kioskscherm',
        'playscope' => 'Playscope-PC',
    ];

    public const PAIRING_TTL_MINUTES = 15;

    /** Toestel geldt als online als het binnen deze termijn nog iets stuurde. */
    public const ONLINE_SECONDS = 120;

    /** Playscope: game afsluiten na zoveel minuten zonder input (idle_minutes null = dit). 0 = uit. */
    public const IDLE_MINUTES_DEFAULT = 10;
    public const IDLE_MINUTES_MIN = 2;
    public const IDLE_MINUTES_MAX = 240;

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'tenant_id', 'setup_id', 'device_group_id', 'type', 'name',
        'pairing_code', 'poll_secret_hash', 'pairing_expires_at', 'claimed_at',
        'token_hash', 'token_issued_at', 'is_active',
        'last_seen_at', 'last_ip', 'hostname', 'platform', 'app_version',
        'state', 'state_updated_at', 'checks', 'idle_minutes', 'rotation_report', 'rotation_reported_at',
    ];

    protected $hidden = ['token_hash', 'poll_secret_hash'];

    protected function casts(): array
    {
        return [
            'pairing_expires_at' => 'datetime',
            'claimed_at'         => 'datetime',
            'token_issued_at'    => 'datetime',
            'is_active'          => 'boolean',
            'last_seen_at'       => 'datetime',
            'state'              => 'array',
            'state_updated_at'   => 'datetime',
            'checks'             => 'array',
            'idle_minutes'       => 'integer',
            'device_group_id'    => 'integer',
            'rotation_report'    => 'array',
            'rotation_reported_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function setup(): BelongsTo
    {
        return $this->belongsTo(Setup::class);
    }

    /** Groep met automatische rotatie (bv. de racers), of null. */
    public function group(): BelongsTo
    {
        return $this->belongsTo(DeviceGroup::class, 'device_group_id');
    }

    /** Opdrachten vanuit de backoffice (bediening op afstand). */
    public function commands(): HasMany
    {
        return $this->hasMany(VenueDeviceCommand::class);
    }

    /** Speelbeurten (statistieken). */
    public function gameSessions(): HasMany
    {
        return $this->hasMany(VenueDeviceGameSession::class);
    }

    /** Minuten zonder input voor Playscope de game afsluit (0 = uit). */
    public function idleMinutes(): int
    {
        return $this->idle_minutes ?? self::IDLE_MINUTES_DEFAULT;
    }

    /**
     * Instellingen die het toestel bij elke heartbeat krijgt.
     * group_id: in een groep met rotatie → Playscope vraagt POST /rotation wat er moet draaien
     * (geen menu, geen afsluiten bij inactiviteit).
     */
    public function deviceSettings(): array
    {
        return [
            'idle_minutes' => $this->device_group_id ? 0 : $this->idleMinutes(),
            'group_id'     => $this->device_group_id,
        ];
    }

    /** Games die Playscope op dit toestel als geïnstalleerd meldde. */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'venue_device_games')->withPivot('installed_at');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(self::ONLINE_SECONDS));
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type] ?? (string) $type;
    }

    public static function hashSecret(string $value): string
    {
        return hash('sha256', $value);
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function generatePairingCode(int $length = 6): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, $max)];
            }
        } while (self::query()->where('pairing_code', $code)->exists());

        return $code;
    }

    /** Publieke weergave voor backoffice en het toestel zelf. */
    public function toPublicArray(): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'type_label'   => self::typeLabel($this->type),
            'name'         => $this->name,
            'setup_id'     => $this->setup_id,
            'setup_name'   => $this->setup?->name,
            'is_active'    => (bool) $this->is_active,
            'online'       => $this->isOnline(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'last_ip'      => $this->last_ip,
            'hostname'     => $this->hostname,
            'platform'     => $this->platform,
            'app_version'  => $this->app_version,
            'state'        => $this->state,
            'checks'       => $this->checks,
            'idle_minutes' => $this->idleMinutes(),
            'device_group_id' => $this->device_group_id,
            'claimed_at'   => $this->claimed_at?->toIso8601String(),
        ];
    }
}
