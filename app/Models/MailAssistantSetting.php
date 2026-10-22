<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAssistantSetting extends Model
{
    public const TONES = ['informal', 'formal', 'follow'];
    public const LANGUAGES = ['customer', 'nl'];
    public const ENCRYPTIONS = ['ssl', 'tls', 'none'];

    /** Welke Playdrive-gegevens de assistent mag gebruiken. */
    public const DEFAULT_SOURCES = [
        'opening_hours' => true,
        'products' => true,
        'options' => true,
        'vouchers' => true,
        'accounts' => true,
        'history' => true,
    ];

    protected $fillable = [
        'tenant_id',
        'enabled',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'imap_folder',
        'sent_folder',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'from_address',
        'from_name',
        'tone',
        'language',
        'signature',
        'instructions',
        'sources',
        'auto_suggest_after',
        'fetch_since_days',
        'uid_validity',
        'last_uid',
        'last_checked_at',
        'last_error',
        'last_error_at',
    ];

    protected $hidden = [
        'imap_password',
        'smtp_password',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'enabled' => 'boolean',
        'imap_port' => 'integer',
        'smtp_port' => 'integer',
        'imap_password' => 'encrypted',
        'smtp_password' => 'encrypted',
        'sources' => 'array',
        'auto_suggest_after' => 'integer',
        'fetch_since_days' => 'integer',
        'uid_validity' => 'integer',
        'last_uid' => 'integer',
        'last_checked_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    protected $attributes = [
        'enabled' => false,
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_folder' => 'INBOX',
        'smtp_port' => 587,
        'tone' => 'informal',
        'language' => 'customer',
        'auto_suggest_after' => 5,
        'fetch_since_days' => 3,
    ];

    public static function forTenant(int $tenantId): self
    {
        return static::query()->firstOrNew(['tenant_id' => $tenantId]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourceFlags(): array
    {
        return array_merge(self::DEFAULT_SOURCES, array_intersect_key((array) ($this->sources ?? []), self::DEFAULT_SOURCES));
    }

    public function usesSource(string $key): bool
    {
        return (bool) ($this->sourceFlags()[$key] ?? false);
    }

    /** Adres waarmee geantwoord wordt (en dat we nooit zelf beantwoorden). */
    public function senderAddress(): ?string
    {
        $address = $this->from_address ?: $this->imap_username;

        return $address && str_contains($address, '@') ? strtolower(trim($address)) : null;
    }

    public function isMailboxConfigured(): bool
    {
        return filled($this->imap_host) && filled($this->imap_username) && filled($this->imap_password);
    }

    public function isSmtpConfigured(): bool
    {
        return filled($this->smtp_host) && filled($this->smtp_username ?: $this->imap_username)
            && filled($this->smtp_password ?: $this->imap_password);
    }
}
