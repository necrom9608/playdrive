<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Inschrijving voor de nieuwsbrief van de venue doorsturen (vinkje bij "lid worden").
 * De venue beheert zelf haar nieuwsbrief (bv. game-inn.be, dubbele opt-in);
 * PlayDrive stuurt enkel e-mail + naam door naar tenants.newsletter_url.
 */
class NewsletterHook
{
    public static function enabled(Tenant $tenant): bool
    {
        return filled($tenant->newsletter_url) && filled($tenant->newsletter_token);
    }

    /** Na het antwoord versturen, zodat een trage of offline site het formulier niet ophoudt. */
    public static function subscribeLater(Tenant $tenant, Account $account): void
    {
        if (! self::enabled($tenant)) {
            return;
        }

        $tenantId  = $tenant->id;
        $accountId = $account->id;

        dispatch(function () use ($tenantId, $accountId) {
            $tenant  = Tenant::query()->find($tenantId);
            $account = Account::query()->find($accountId);

            if ($tenant && $account) {
                NewsletterHook::subscribe($tenant, $account);
            }
        })->afterResponse();
    }

    public static function subscribe(Tenant $tenant, Account $account): bool
    {
        if (! self::enabled($tenant)) {
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(4)
                ->acceptJson()
                ->asJson()
                ->withToken((string) $tenant->newsletter_token)
                ->post($tenant->newsletter_url, [
                    'email' => $account->email,
                    'naam'  => $account->full_name,
                    'taal'  => 'nl',
                ]);

            if (! $response->successful()) {
                Log::warning('Nieuwsbrief-inschrijving geweigerd', [
                    'tenant_id'  => $tenant->id,
                    'account_id' => $account->id,
                    'status'     => $response->status(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Nieuwsbrief-inschrijving mislukt', [
                'tenant_id'  => $tenant->id,
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }
}
