<?php

namespace App\Console\Commands;

use App\Domain\Accounting\AccountingExportService;
use App\Models\AccountingExport;
use App\Models\AccountingExportSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class SendAccountingDayTotalsCommand extends Command
{
    protected $signature = 'accounting:send-day-totals
        {--dry-run : Toon wat er verstuurd zou worden zonder effectief te verzenden}
        {--tenant= : Enkel deze tenant (id of slug)}
        {--date= : Doen alsof het vandaag deze datum is (Y-m-d), om te testen}';

    protected $description = 'Verstuurt de dagtotalen automatisch naar de boekhouding (per maand of kwartaal, volgens de instellingen per tenant).';

    public function handle(AccountingExportService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $query = AccountingExportSetting::query()->where('enabled', true);

        if ($tenantOption = $this->option('tenant')) {
            $tenant = Tenant::query()->where('id', $tenantOption)->orWhere('slug', $tenantOption)->first();

            if (! $tenant) {
                $this->error("Tenant '{$tenantOption}' niet gevonden.");

                return self::FAILURE;
            }

            $query->where('tenant_id', $tenant->id);
        }

        $sent = 0;
        $failed = 0;
        $prefix = $dryRun ? '[DRY RUN] ' : '';

        foreach ($query->get() as $settings) {
            $tenant = Tenant::query()->where('is_active', true)->find($settings->tenant_id);

            if (! $tenant) {
                continue;
            }

            $period = $service->duePeriod($settings, $today);

            if (! $period) {
                $this->line("{$tenant->name}: niets te versturen.");
                continue;
            }

            $to = implode(', ', $settings->recipientList());
            $this->line("{$prefix}{$tenant->name}: {$period->label()} → {$to}");

            if ($dryRun) {
                continue;
            }

            try {
                $service->send($tenant, $period, AccountingExport::TRIGGER_AUTO, null, $settings);
                $sent++;
            } catch (Throwable $e) {
                $this->error("   mislukt: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("{$prefix}Verstuurd: {$sent}" . ($failed ? " · mislukt: {$failed}" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
