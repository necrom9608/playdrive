<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Verticaal filmpje voor de standby van het klantenscherm.
 * Bestanden staan op de disk "local" onder display-videos/{tenant}/{id}/.
 */
class DisplayVideo extends Model
{
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    /** Icoontjes in de orbit van de Game-INN-skin (zelfde volgorde als op de website). */
    public const ZONES = ['arcade', 'vr', 'racing', 'rhythm', 'consoles', 'esports', 'boardgames', 'retro'];

    protected $fillable = [
        'tenant_id', 'title', 'zone', 'status', 'original_name', 'upload_path', 'path',
        'upload_size', 'size_bytes', 'duration_seconds', 'note', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'upload_size'      => 'integer',
            'size_bytes'       => 'integer',
            'duration_seconds' => 'float',
            'is_active'        => 'boolean',
            'sort_order'       => 'integer',
        ];
    }

    public function folder(): string
    {
        return "display-videos/{$this->tenant_id}/{$this->id}";
    }

    public function isPlayable(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->path
            && Storage::disk('local')->exists($this->path);
    }

    /** Afspeel-URL voor het scherm; ?v wisselt als het bestand verandert, zodat de cache lang mag blijven. */
    public function publicUrl(): string
    {
        return url("/display/media/videos/{$this->id}").'?v='.substr(md5((string) $this->path), 0, 10);
    }

    public function toPublicArray(): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'zone'             => $this->zone,
            'status'           => $this->status,
            'original_name'    => $this->original_name,
            'upload_size'      => $this->upload_size,
            'size_bytes'       => $this->size_bytes,
            'duration_seconds' => $this->duration_seconds,
            'note'             => $this->note,
            'is_active'        => $this->is_active,
            'sort_order'       => $this->sort_order,
            'url'              => $this->status === self::STATUS_READY ? $this->publicUrl() : null,
        ];
    }
}
