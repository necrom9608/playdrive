<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Een rhythm-server (Asphyxia + playdrive-sync) die scores pusht voor een venue. */
class ArcadeSource extends Model
{
    protected $fillable = ['tenant_id', 'name', 'token_hash', 'is_active', 'last_seen_at', 'last_host'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
