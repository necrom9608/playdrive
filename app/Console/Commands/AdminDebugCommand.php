<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminDebugCommand extends Command
{
    protected $signature   = 'admin:debug';
    protected $description = 'Tijdelijk debug-commando om de admin config te controleren';

    public function handle(): int
    {
        $username = (string) env('PLAYDRIVE_ADMIN_USERNAME', '(niet ingesteld)');
        $password = (string) env('PLAYDRIVE_ADMIN_PASSWORD', '');
        $hash     = trim((string) env('PLAYDRIVE_ADMIN_PASSWORD_HASH', ''), '"\'');

        $this->line('');
        $this->info('=== Admin config debug ===');
        $this->line('');
        $this->line("USERNAME:      {$username}");
        $this->line("PASSWORD:      " . ($password !== '' ? '(ingesteld, plaintext)' : '(leeg)'));
        $this->line("PASSWORD_HASH: " . ($hash !== '' ? substr($hash, 0, 7) . '...' . substr($hash, -6) . ' (' . strlen($hash) . ' tekens)' : '(leeg)'));
        $this->line('');

        if ($hash !== '') {
            $isValidHash = strlen($hash) === 60 && str_starts_with($hash, '$2y$');
            $this->line('Hash geldig formaat: ' . ($isValidHash ? '✓ ja' : '✗ nee — hash is corrupt!'));

            if (! $isValidHash) {
                $this->error('De hash in je .env of config cache is corrupt. Voer admin:set-password opnieuw uit en run daarna config:clear (NIET config:cache).');
            }
        } else {
            $this->warn('Geen hash ingesteld — login werkt via plaintext PLAYDRIVE_ADMIN_PASSWORD.');
        }

        $this->line('');

        return self::SUCCESS;
    }
}
