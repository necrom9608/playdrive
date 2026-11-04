<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aankoop van een particulier + het bijhorende aankoopborderel.
 *
 * Levensloop: draft → sent (link/QR actief) → signed (PDF gemaakt en gemaild) → paid.
 * Cancelled kan zolang er niet betaald is. Na ondertekening is de inhoud vergrendeld.
 */
class PrivatePurchase extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_TRANSFER = 'transfer';
    public const PAYMENT_CASH = 'cash';

    /** Cash-betalingen bij aankoop van goederen: wettelijk maximum in België. */
    public const CASH_LIMIT = 3000.00;

    /** Geldigheid van de link in dagen. */
    public const LINK_DAYS = 7;

    /** Weergave van datums en tijden op het borderel. */
    public const TIMEZONE = 'Europe/Brussels';

    public const DECLARATIONS = [
        'owner' => 'Ik ben de eigenaar van deze goederen en ze zijn vrij van lasten, beslag of eigendomsvoorbehoud.',
        'private' => 'Ik verkoop als particulier, niet in het kader van een beroeps- of handelsactiviteit.',
        'price' => 'Ik ga akkoord met de verkoop aan de vermelde prijs.',
    ];

    protected $fillable = [
        'tenant_id',
        'year',
        'sequence',
        'number',
        'status',
        'token',
        'token_expires_at',
        'sent_via',
        'invite_email',
        'invited_at',
        'opened_at',
        'items',
        'total_amount',
        'payment_method',
        'purchase_date',
        'sign_place',
        'photos',
        'internal_note',
        'mail_seller',
        'mail_tenant',
        'mail_accounting',
        'extra_recipients',
        'seller_first_name',
        'seller_last_name',
        'seller_street',
        'seller_postal_code',
        'seller_city',
        'seller_country',
        'seller_email',
        'seller_phone',
        'seller_iban',
        'seller_iban_holder',
        'declarations',
        'signature',
        'signed_at',
        'signed_ip',
        'signed_user_agent',
        'document_hash',
        'pdf_path',
        'pdf_hash',
        'mailed_at',
        'mailed_to',
        'mail_error',
        'paid_at',
        'cancelled_at',
        'created_by',
    ];

    protected $hidden = [
        'token',
        'signature',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'year' => 'integer',
        'sequence' => 'integer',
        'items' => 'array',
        'total_amount' => 'decimal:2',
        'purchase_date' => 'date',
        'photos' => 'array',
        'mail_seller' => 'boolean',
        'mail_tenant' => 'boolean',
        'mail_accounting' => 'boolean',
        'extra_recipients' => 'array',
        'declarations' => 'array',
        'signature' => 'array',
        'mailed_to' => 'array',
        'token_expires_at' => 'datetime',
        'invited_at' => 'datetime',
        'opened_at' => 'datetime',
        'signed_at' => 'datetime',
        'mailed_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'payment_method' => self::PAYMENT_TRANSFER,
        'mail_seller' => true,
        'mail_tenant' => true,
        'mail_accounting' => true,
    ];

    // ------------------------------------------------------------------
    // Relaties
    // ------------------------------------------------------------------

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ------------------------------------------------------------------
    // Status
    // ------------------------------------------------------------------

    /** Na ondertekening (of annulatie) kan de inhoud niet meer gewijzigd worden. */
    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_SIGNED, self::STATUS_PAID, self::STATUS_CANCELLED], true);
    }

    public function isSigned(): bool
    {
        return in_array($this->status, [self::STATUS_SIGNED, self::STATUS_PAID], true) && $this->signed_at !== null;
    }

    public function linkIsActive(): bool
    {
        return $this->status === self::STATUS_SENT
            && $this->token
            && (! $this->token_expires_at || $this->token_expires_at->isFuture());
    }

    // ------------------------------------------------------------------
    // Weergave
    // ------------------------------------------------------------------

    public function publicUrl(): ?string
    {
        return $this->token ? url('/borderel/' . $this->token) : null;
    }

    public function sellerName(): ?string
    {
        $name = trim(($this->seller_first_name ?? '') . ' ' . ($this->seller_last_name ?? ''));

        return $name !== '' ? $name : null;
    }

    public function itemsSummary(int $max = 2): string
    {
        $items = collect($this->items ?? [])->pluck('description')->filter()->values();
        $summary = $items->take($max)->implode(', ');

        return $items->count() > $max ? $summary . ' + ' . ($items->count() - $max) . ' meer' : $summary;
    }

    public function maskedIban(): ?string
    {
        if (! $this->seller_iban) {
            return null;
        }
        $iban = preg_replace('/\s+/', '', $this->seller_iban);

        return substr($iban, 0, 4) . ' •••• •••• ' . substr($iban, -4);
    }

    public static function formatIban(?string $iban): ?string
    {
        if (! $iban) {
            return null;
        }

        return trim(chunk_split(strtoupper(preg_replace('/\s+/', '', $iban)), 4, ' '));
    }

    public static function money(float|string|null $value): string
    {
        return '€ ' . number_format((float) $value, 2, ',', '.');
    }

    public function paymentLabel(): string
    {
        return $this->payment_method === self::PAYMENT_CASH ? 'Contant' : 'Overschrijving';
    }
}
