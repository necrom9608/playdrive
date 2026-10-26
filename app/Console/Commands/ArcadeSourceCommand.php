<?php

namespace App\Console\Commands;

use App\Models\ArcadeSource;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Tokens voor playdrive-sync (Asphyxia-servers).
 *
 *   php artisan arcade:source game-inn "Asphyxia gameinnserver"   nieuwe bron + token tonen
 *   php artisan arcade:source --list                              bronnen tonen
 *   php artisan arcade:source --revoke=3                          bron uitschakelen
 */
class ArcadeSourceCommand extends Command
{
    protected $signature = 'arcade:source {tenant? : tenant-id of slug} {name? : naam van de server}
                            {--list : bestaande bronnen tonen}
                            {--revoke= : bron (id) uitschakelen}';

    protected $description = 'Token aanmaken/intrekken voor een Asphyxia-server (playdrive-sync)';

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->table(
                ['id', 'tenant', 'naam', 'actief', 'laatst gezien', 'host'],
                ArcadeSource::query()->with('tenant:id,name')->orderBy('id')->get()->map(fn ($s) => [
                    $s->id, $s->tenant?->name, $s->name, $s->is_active ? 'ja' : 'nee',
                    $s->last_seen_at?->format('Y-m-d H:i:s') ?? '-', $s->last_host ?? '-',
                ])
            );

            return self::SUCCESS;
        }

        if ($id = $this->option('revoke')) {
            $source = ArcadeSource::query()->find($id);
            if (! $source) {
                $this->error("Bron $id bestaat niet.");

                return self::FAILURE;
            }
            $source->update(['is_active' => false]);
            $this->info("Bron $id ({$source->name}) is ingetrokken.");

            return self::SUCCESS;
        }

        $tenantArg = (string) $this->argument('tenant');
        $tenant = Tenant::query()
            ->when(ctype_digit($tenantArg), fn ($q) => $q->whereKey((int) $tenantArg), fn ($q) => $q->where('slug', $tenantArg))
            ->first();
        if (! $tenant) {
            $this->error('Tenant niet gevonden (geef een id of slug).');

            return self::FAILURE;
        }

        $token = 'pda_' . Str::random(48);
        $source = ArcadeSource::query()->create([
            'tenant_id'  => $tenant->id,
            'name'       => (string) ($this->argument('name') ?: 'Asphyxia'),
            'token_hash' => ArcadeSource::hashToken($token),
        ]);

        $this->info("Bron {$source->id} aangemaakt voor {$tenant->name}.");
        $this->line('Zet dit in /etc/playdrive-sync.env op de server (wordt maar één keer getoond):');
        $this->newLine();
        $this->line("PLAYDRIVE_TOKEN=$token");
        $this->newLine();

        return self::SUCCESS;
    }
}
