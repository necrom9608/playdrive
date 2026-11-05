<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Account is de globale identiteit van een PlayDrive-gebruiker.
 * Eén account kan gekoppeld zijn aan meerdere tenants via TenantMembership.
 *
 * Uitgebreid met HasApiTokens voor de member-api Sanctum token auth.
 * Uitgebreid met MustVerifyEmail voor e-mailbevestiging bij registratie.
 */
class Account extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'managed_by_account_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'birth_date',
        'street',
        'house_number',
        'box',
        'postal_code',
        'city',
        'country',
        'password',
        'arcade_notify_mail',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'birth_date'        => 'date',
            'password'          => 'hashed',
            'email_verified_at' => 'datetime',
            'arcade_notify_mail' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /** Account dat dit (beheerde) account beheert, bv. ouder van een kind zonder e-mail. */
    public function managedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'managed_by_account_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Eigen resetmail i.p.v. Laravels standaard ResetPassword-notificatie:
     * die verwacht een route 'password.reset' die Playdrive niet heeft.
     * De link wijst naar de member app, net als de uitnodigingsmail.
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = url('/member#/reset-password?' . http_build_query([
            'token' => $token,
            'email' => $this->email,
        ]));

        $mailable = new \App\Mail\PasswordResetMail($this, $resetUrl);

        \Illuminate\Support\Facades\Mail::to($this->email, $this->full_name)->send($mailable);

        \App\Services\MailLogger::log(
            toEmail:   $this->email,
            subject:   'Stel een nieuw wachtwoord in voor je PlayDrive account',
            toName:    $this->full_name,
            accountId: $this->id,
            mailType:  'password_reset',
            htmlBody:  $mailable->render(),
        );
    }
}
