<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AdminDebugCommand extends Command
{
    protected $signature   = 'admin:debug';
    protected $description = 'Tijdelijk debug-commando om de admin config te controleren';

    public function handle(): int
    {
        $username = (string) config('playdrive-admin.username', '(niet ingesteld)');
        $password = (string) config('playdrive-admin.password', '');
        $hash     = trim((string) config('playdrive-admin.password_hash', ''), '"\'');

        $this->line('');
        $this->info('=== Admin config debug ===');
        $this->line('');
        $this->line("USERNAME:      {$username}");
        $this->line("PASSWORD:      " . ($password !== '' && $password !== 'change-me' ? '(ingesteld, plaintext)' : '(leeg of standaard)'));
        $this->line("PASSWORD_HASH: " . ($hash !== '' ? substr($hash, 0, 7) . '...' . substr($hash, -6) . ' (' . strlen($hash) . ' tekens)' : '(leeg)'));
        $this->line('');

        if ($hash !== '') {
            $isValidHash = strlen($hash) === 60 && str_starts_with($hash, '$2y$');
            $this->line('Hash geldig formaat: ' . ($isValidHash ? '✓ ja' : '✗ nee — hash is corrupt!'));
        } else {
            $this->warn('Geen hash ingesteld — login werkt via plaintext PLAYDRIVE_ADMIN_PASSWORD.');
        }

        $this->line('');

        return self::SUCCESS;
    }
}
