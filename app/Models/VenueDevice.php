<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'tenant_id', 'setup_id', 'type', 'name',
        'pairing_code', 'poll_secret_hash', 'pairing_expires_at', 'claimed_at',
        'token_hash', 'token_issued_at', 'is_active',
        'last_seen_at', 'last_ip', 'hostname', 'platform', 'app_version',
        'state', 'state_updated_at',
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
            'claimed_at'   => $this->claimed_at?->toIso8601String(),
        ];
    }
}
