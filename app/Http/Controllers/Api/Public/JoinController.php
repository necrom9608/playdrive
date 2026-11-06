<?php

namespace App\Http\Controllers\Api\Public;

use App\Domain\Display\DisplaySkin;
use App\Http\Controllers\Controller;
use App\Mail\JoinWelcomeMail;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\MailLogger;
use App\Support\JoinLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Lid worden van een venue via de QR-code op het klantenscherm: /join/{slug}.
 *
 *   GET  /api/join/{slug}            venue (naam, logo, stijl)
 *   POST /api/join/{slug}/register   nieuw account zonder wachtwoord (+ kinderen) → lid
 *   POST /api/join/{slug}/login      bestaand Playdrive-account → lid
 *   POST /api/join/{slug}/resend     bevestigingsmail opnieuw
 *
 * Lid worden = een TenantMembership zonder abonnement (is_active = false).
 * Een abonnement wordt apart gekoppeld aan de frontdesk.
 * Wachtwoord kiezen + e-mail bevestigen gebeurt via de link in de mail
 * (member app → reset-password, zet ook email_verified_at).
 */
class JoinController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $tenant = $this->tenant($slug);

        return response()->json(['data' => $this->venue($tenant)]);
    }

    public function register(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->tenant($slug);

        $data = $request->validate([
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255'],
            'birth_date'            => ['nullable', 'date', 'before:today'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'accept_terms'          => ['accepted'],
            'children'              => ['nullable', 'array', 'max:8'],
            'children.*.first_name' => ['required', 'string', 'max:255'],
            'children.*.last_name'  => ['nullable', 'string', 'max:255'],
            'children.*.birth_date' => ['nullable', 'date', 'before:today'],
        ], [
            'accept_terms.accepted'          => 'Ga akkoord om lid te worden.',
            'children.*.first_name.required' => 'Vul de voornaam van elk kind in.',
        ]);

        $email   = strtolower(trim($data['email']));
        $account = Account::query()->where('email', $email)->first();

        // Account met wachtwoord: laten inloggen i.p.v. een tweede account.
        if ($account && filled($account->password)) {
            return response()->json([
                'code'    => 'account_exists',
                'message' => 'Er bestaat al een Playdrive-account met dit e-mailadres. Log in om lid te worden.',
            ], 409);
        }

        [$membership, $account] = DB::transaction(function () use ($data, $email, $account, $tenant) {
            // Account zonder wachtwoord (vorige registratie nooit bevestigd): hergebruiken, gegevens niet overschrijven.
            $account ??= Account::query()->create([
                'email'      => $email,
                'first_name' => trim($data['first_name']),
                'last_name'  => trim($data['last_name']),
                'birth_date' => $data['birth_date'] ?? null,
                'phone'      => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            ]);

            $membership = $this->ensureMembership($account, $tenant);

            foreach ($data['children'] ?? [] as $child) {
                $this->addChild($account, $tenant, $child);
            }

            return [$membership, $account];
        });

        $mailSent = $this->sendWelcomeMail($account, $tenant, $membership);

        return response()->json([
            'data' => $this->result($membership, $account, $mailSent, needsConfirmation: true),
        ], 201);
    }

    public function login(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->tenant($slug);

        $data = $request->validate([
            'email'        => ['required', 'email'],
            'password'     => ['required', 'string'],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Ga akkoord om lid te worden.',
        ]);

        $account = Account::query()->where('email', strtolower(trim($data['email'])))->first();

        if (! $account || ! filled($account->password) || ! Hash::check($data['password'], $account->password)) {
            return response()->json([
                'message' => 'Deze combinatie van e-mail en wachtwoord is niet correct.',
                'errors'  => ['email' => ['Deze combinatie van e-mail en wachtwoord is niet correct.']],
            ], 422);
        }

        $already    = TenantMembership::query()->where('account_id', $account->id)->where('tenant_id', $tenant->id)->exists();
        $membership = $this->ensureMembership($account, $tenant);

        return response()->json([
            'data' => [
                ...$this->result($membership, $account, false, needsConfirmation: ! $account->hasVerifiedEmail()),
                'already_member' => $already,
            ],
        ], $already ? 200 : 201);
    }

    public function resend(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->tenant($slug);

        $data = $request->validate([
            'membership_id' => ['required', 'integer'],
            'email'         => ['required', 'email'],
        ]);

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($data['membership_id'])
            ->first();

        $account = $membership?->account;

        // Enkel voor dit lid en zolang er nog geen wachtwoord is; altijd hetzelfde antwoord.
        if ($account && strtolower(trim($data['email'])) === $account->email && ! filled($account->password)) {
            $this->sendWelcomeMail($account, $tenant, $membership);
        }

        return response()->json(['message' => 'Als alles klopt, ontvang je zo een nieuwe mail.']);
    }

    // ------------------------------------------------------------------

    private function tenant(string $slug): Tenant
    {
        $tenant = JoinLink::tenantFor($slug);
        abort_unless($tenant, 404, 'Deze zaak bestaat niet (meer).');

        return $tenant;
    }

    private function venue(Tenant $tenant): array
    {
        $skin = DisplaySkin::settingsFor($tenant->id)->skin;

        return [
            'slug'     => JoinLink::slug($tenant),
            'name'     => $tenant->display_name,
            'logo_url' => $tenant->logo_url,
            'city'     => $tenant->city,
            // Huisstijl van het formulier volgt de skin van het klantenscherm.
            'theme'    => $skin === 'gameinn' ? 'gameinn' : 'standaard',
        ];
    }

    /** Lid worden: bestaande koppeling blijft ongemoeid (ook een actief abonnement). */
    private function ensureMembership(Account $account, Tenant $tenant): TenantMembership
    {
        return TenantMembership::query()->firstOrCreate(
            ['account_id' => $account->id, 'tenant_id' => $tenant->id],
            ['membership_type' => TenantMembership::TYPE_ADULT, 'is_active' => false],
        );
    }

    /** Kind zonder e-mail: beheerd account onder de ouder, zelf ook lid (zonder abonnement). */
    private function addChild(Account $parent, Tenant $tenant, array $child): void
    {
        $firstName = trim($child['first_name']);
        $lastName  = trim((string) ($child['last_name'] ?? '')) ?: $parent->last_name;

        $account = Account::query()
            ->where('managed_by_account_id', $parent->id)
            ->where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->first();

        $account ??= Account::query()->create([
            'managed_by_account_id' => $parent->id,
            'email'                 => $this->placeholderEmail($parent),
            'first_name'            => $firstName,
            'last_name'             => $lastName,
            'birth_date'            => $child['birth_date'] ?? null,
            'street'                => $parent->street,
            'house_number'          => $parent->house_number,
            'box'                   => $parent->box,
            'postal_code'           => $parent->postal_code,
            'city'                  => $parent->city,
            'country'               => $parent->country,
        ]);

        $this->ensureMembership($account, $tenant);
    }

    private function placeholderEmail(Account $parent): string
    {
        do {
            $email = sprintf('kind-%d-%s@playdrive.local', $parent->id, Str::lower(Str::random(8)));
        } while (Account::query()->where('email', $email)->exists());

        return $email;
    }

    /** Welkomstmail met de link om het e-mailadres te bevestigen en een wachtwoord te kiezen. */
    private function sendWelcomeMail(Account $account, Tenant $tenant, TenantMembership $membership): bool
    {
        try {
            $token    = Password::broker('accounts')->createToken($account);
            $setupUrl = url('/member#/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $account->email,
            ]));

            $mailable = new JoinWelcomeMail($account, $setupUrl, $tenant, self::code($membership), $this->venue($tenant)['theme']);

            Mail::to($account->email, $account->full_name)->send($mailable);

            MailLogger::log(
                toEmail:   $account->email,
                subject:   $mailable->subjectLine(),
                toName:    $account->full_name,
                tenantId:  $tenant->id,
                accountId: $account->id,
                mailType:  'join_welcome',
                htmlBody:  $mailable->render(),
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Welkomstmail (join) mislukt', ['account_id' => $account->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function result(TenantMembership $membership, Account $account, bool $mailSent, bool $needsConfirmation): array
    {
        $children = Account::query()
            ->where('managed_by_account_id', $account->id)
            ->whereHas('memberships', fn ($q) => $q->where('tenant_id', $membership->tenant_id))
            ->orderBy('id')
            ->get(['first_name', 'birth_date'])
            ->map(fn (Account $c) => [
                'first_name' => $c->first_name,
                'age'        => $c->birth_date ? Carbon::parse($c->birth_date)->age : null,
            ])->values();

        return [
            'membership_id'      => $membership->id,
            'code'               => self::code($membership),
            'first_name'         => $account->first_name,
            'email'              => $account->email,
            'needs_confirmation' => $needsConfirmation,
            'mail_sent'          => $mailSent,
            'children'           => $children,
        ];
    }

    /** Code in de persoonlijke QR: de frontdesk scant hem in het zoekveld bij Leden. */
    public static function code(TenantMembership $membership): string
    {
        return 'PDL-'.$membership->id;
    }
}
