<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminSetPasswordCommand extends Command
{
    protected $signature   = 'admin:set-password';
    protected $description = 'Stel het admin-paswoord in voor het PlayDrive admin-panel (/admin)';

    public function handle(): int
    {
        $this->info('PlayDrive admin-panel — inloggegevens instellen');
        $this->line('');

        // Username
        $currentUsername = env('PLAYDRIVE_ADMIN_USERNAME', 'admin');
        $username = $this->ask('Gebruikersnaam', $currentUsername);

        // Password
        $password = $this->secret('Nieuw paswoord (min. 8 tekens)');

        if (strlen($password) < 8) {
            $this->error('Paswoord moet minstens 8 tekens lang zijn.');
            return self::FAILURE;
        }

        $confirm = $this->secret('Bevestig paswoord');

        if ($password !== $confirm) {
            $this->error('Paswoorden komen niet overeen.');
            return self::FAILURE;
        }

        $hash = Hash::make($password);

        $this->line('');
        $this->info('Voeg de volgende regels toe aan je .env:');
        $this->line('');
        $this->line("PLAYDRIVE_ADMIN_USERNAME={$username}");
        $this->line("PLAYDRIVE_ADMIN_PASSWORD_HASH={$hash}");
        $this->line('# Verwijder of leeg PLAYDRIVE_ADMIN_PASSWORD als je de hash gebruikt.');
        $this->line('');

        if ($this->confirm('Automatisch schrijven naar .env?', true)) {
            $this->writeToEnv('PLAYDRIVE_ADMIN_USERNAME', $username);
            $this->writeToEnv('PLAYDRIVE_ADMIN_PASSWORD_HASH', $hash);
            $this->writeToEnv('PLAYDRIVE_ADMIN_PASSWORD', '');
            $this->info('✓ .env bijgewerkt. Vergeet niet je config cache te legen: php artisan config:clear');
        }

        return self::SUCCESS;
    }

    private function writeToEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->warn(".env niet gevonden op {$envPath}");
            return;
        }

        $content = file_get_contents($envPath);

        // Escape value if it contains spaces or special chars
        $escapedValue = preg_match('/\s/', $value) ? "\"{$value}\"" : $value;

        if (preg_match("/^{$key}=.*/m", $content)) {
            // Key bestaat — vervang
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$escapedValue}", $content);
        } else {
            // Key bestaat niet — voeg toe onderaan
            $content .= PHP_EOL . "{$key}={$escapedValue}";
        }

        file_put_contents($envPath, $content);
    }
}
