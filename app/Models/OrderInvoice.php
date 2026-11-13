<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Factuur van een bestelling in WeFact. Zie App\Domain\Invoicing\OrderInvoiceService. */
class OrderInvoice extends Model
{
    public const STATUS_DRAFT           = 'draft';   // gegevens opgeslagen, nog niet naar WeFact
    public const STATUS_PENDING         = 'pending';
    public const STATUS_MISSING_DETAILS = 'missing_details';
    public const STATUS_ERROR           = 'error';
    public const STATUS_DONE            = 'done';

    public const CHANNEL_PEPPOL = 'peppol';
    public const CHANNEL_EMAIL  = 'email';

    protected $fillable = [
        'tenant_id', 'order_id', 'status', 'channel',
        'company_name', 'contact_name', 'vat_number', 'email', 'address', 'postal_code', 'city', 'country',
        'po_number', 'comment',
        'wefact_debtor_code', 'wefact_invoice_id', 'wefact_invoice_code',
        'sent_at', 'paid_marked_at', 'error', 'attempts', 'created_by',
        'payment_status', 'amount_outstanding', 'due_date', 'reminders_count', 'last_reminder_at', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at'        => 'datetime',
            'paid_marked_at' => 'datetime',
            'attempts'       => 'integer',
            'amount_outstanding' => 'decimal:2',
            'due_date'           => 'date',
            'reminders_count'    => 'integer',
            'last_reminder_at'   => 'datetime',
            'synced_at'          => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function toFrontdeskArray(): array
    {
        return [
            'id'             => $this->id,
            'status'         => $this->status,
            'channel'        => $this->channel,
            'invoice_code'   => $this->wefact_invoice_code,
            'company_name'   => $this->company_name,
            'contact_name'   => $this->contact_name,
            'vat_number'     => $this->vat_number,
            'email'          => $this->email,
            'address'        => $this->address,
            'postal_code'    => $this->postal_code,
            'city'           => $this->city,
            'country'        => $this->country,
            'po_number'      => $this->po_number,
            'comment'        => $this->comment,
            'sent_at'        => $this->sent_at?->toIso8601String(),
            'paid_marked_at' => $this->paid_marked_at?->toIso8601String(),
            'error'          => $this->error,
            'in_wefact'      => (bool) $this->wefact_invoice_id,
            'payment_status'     => $this->payment_status,
            'amount_outstanding' => $this->amount_outstanding !== null ? (float) $this->amount_outstanding : null,
            'due_date'           => $this->due_date?->toDateString(),
            'reminders_count'    => (int) $this->reminders_count,
            'last_reminder_at'   => $this->last_reminder_at?->toIso8601String(),
            'synced_at'          => $this->synced_at?->toIso8601String(),
        ];
    }
}
