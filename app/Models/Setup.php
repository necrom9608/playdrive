<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fysieke speelplek in een venue (arcadekast, LAN-PC, couch-setup, ...).
 * De QR-code is globaal uniek zodat /s/{qr_code} zonder tenant-context werkt.
 */
class Setup extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_ARCHIVED = 'archived';

    /** Zonder verwarrende tekens (0/o, 1/l/i). */
    private const QR_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    protected $fillable = [
        'tenant_id', 'name', 'type', 'zone', 'description', 'games',
        'max_players', 'status', 'qr_code', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'games'       => 'array',
            'max_players' => 'integer',
            'sort_order'  => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(VenueDevice::class);
    }

    public function qrUrl(): string
    {
        return url('/s/' . $this->qr_code);
    }

    public static function generateQrCode(int $length = 8): string
    {
        $max = strlen(self::QR_ALPHABET) - 1;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::QR_ALPHABET[random_int(0, $max)];
            }
        } while (self::query()->where('qr_code', $code)->exists());

        return $code;
    }
}
