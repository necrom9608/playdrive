<?php

namespace App\Models;

use App\Domain\Setups\DeviceCommands;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Opdracht vanuit de backoffice naar een toestel (zie App\Domain\Setups\DeviceCommands). */
class VenueDeviceCommand extends Model
{
    protected $fillable = [
        'tenant_id', 'venue_device_id', 'command', 'payload', 'status', 'result',
        'created_by', 'sent_at', 'done_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(VenueDevice::class, 'venue_device_id');
    }

    /** Voor de backoffice. */
    public function toPublicArray(): array
    {
        return [
            'id'         => $this->id,
            'command'    => $this->command,
            'label'      => DeviceCommands::label($this->command),
            'payload'    => $this->payload,
            'status'     => $this->status,
            'result'     => $this->result,
            'created_at' => $this->created_at?->toIso8601String(),
            'done_at'    => $this->done_at?->toIso8601String(),
        ];
    }

    /** Voor het toestel. */
    public function toDeviceArray(): array
    {
        return [
            'id'      => $this->id,
            'command' => $this->command,
            'payload' => $this->payload ?? (object) [],
        ];
    }
}
