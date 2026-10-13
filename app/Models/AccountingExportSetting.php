<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingExportSetting extends Model
{
    public const FREQUENCY_MONTH = 'month';
    public const FREQUENCY_QUARTER = 'quarter';

    public const DEFAULT_VISIBLE_GROUPS = [
        'rate0' => false,
        'rate6' => true,
        'rate12' => false,
        'rate21' => true,
        'total' => true,
    ];

    protected $fillable = [
        'tenant_id',
        'enabled',
        'enabled_at',
        'recipients',
        'cc',
        'frequency',
        'send_day',
        'include_invoices',
        'include_payment_methods',
        'visible_groups',
        'message',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'enabled' => 'boolean',
        'enabled_at' => 'datetime',
        'recipients' => 'array',
        'cc' => 'array',
        'send_day' => 'integer',
        'include_invoices' => 'boolean',
        'include_payment_methods' => 'boolean',
        'visible_groups' => 'array',
    ];

    protected $attributes = [
        'enabled' => false,
        'frequency' => self::FREQUENCY_QUARTER,
        'send_day' => 5,
        'include_invoices' => false,
        'include_payment_methods' => true,
    ];

    public static function forTenant(int $tenantId): self
    {
        return static::query()->firstOrNew(['tenant_id' => $tenantId]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recipientList(): array
    {
        return array_values(array_filter((array) ($this->recipients ?? [])));
    }

    public function ccList(): array
    {
        return array_values(array_filter((array) ($this->cc ?? [])));
    }

    public function visibleGroups(): array
    {
        return array_merge(self::DEFAULT_VISIBLE_GROUPS, (array) ($this->visible_groups ?? []));
    }
}
