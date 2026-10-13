<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingExport extends Model
{
    public const TRIGGER_AUTO = 'auto';
    public const TRIGGER_MANUAL = 'manual';

    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'period_type',
        'period_start',
        'period_end',
        'period_label',
        'trigger',
        'recipients',
        'cc',
        'status',
        'error',
        'total_incl_vat',
        'order_count',
        'filename',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'recipients' => 'array',
        'cc' => 'array',
        'total_incl_vat' => 'decimal:2',
        'order_count' => 'integer',
        'sent_by' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
