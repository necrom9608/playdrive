<?php

namespace App\Console\Commands;

use App\Domain\MailAssistant\MailboxFetcher;
use App\Models\MailAssistantMessage;
use App\Models\MailAssistantSetting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * Mailassistent: nieuwe mails ophalen en er een concept-antwoord voor laten opstellen.
 * Draait via de scheduler elke 2 minuten voor alle tenants waar de assistent aan staat.
 *
 *   php artisan mail-assistant:fetch                 alle actieve tenants
 *   php artisan mail-assistant:fetch --tenant=1      één tenant
 *   php artisan mail-assistant:fetch --no-draft      enkel ophalen, geen Claude-aanroep
 */
class MailAssistantFetchCommand extends Command
{
    protected $signature = 'mail-assistant:fetch {--tenant= : Enkel deze tenant-id} {--no-draft : Geen concepten opstellen}';

    protected $description = 'Haalt nieuwe klantmails op en stelt concept-antwoorden op (mailassistent)';

    public function handle(MailboxFetcher $fetcher): int
    {
        $query = MailAssistantSetting::query()->where('enabled', true);

        if ($this->option('tenant')) {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }

        $failed = 0;

        foreach ($query->get() as $settings) {
            $tenant = Tenant::find($settings->tenant_id);
            if (! $tenant || ! $tenant->is_active || ! $settings->isMailboxConfigured()) {
                continue;
            }

            try {
                $stats = $fetcher->run($tenant, $settings, ! $this->option('no-draft'));
                $this->line(sprintf(
                    '%s: %d opgehaald, %d concepten, %d genegeerd, %d fouten',
                    $tenant->display_name,
                    $stats['fetched'],
                    $stats['drafted'],
                    $stats['ignored'],
                    $stats['errors']
                ));
            } catch (Throwable $e) {
                $failed++;
                $this->error($tenant->display_name . ': ' . $e->getMessage());
            }

            // Mails die bij een vorige run bleven hangen (bv. API even onbereikbaar) opnieuw proberen
            if (! $this->option('no-draft')) {
                MailAssistantMessage::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', MailAssistantMessage::STATUS_NEW)
                    ->where('created_at', '<', now()->subMinutes(5))
                    ->limit(5)
                    ->get()
                    ->each(fn (MailAssistantMessage $message) => $fetcher->draftSafely($tenant, $settings, $message));
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
