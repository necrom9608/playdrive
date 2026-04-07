<?php

namespace App\Console\Commands;

use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportLegacyMembersCommand extends Command
{
    protected $signature = 'playdrive:import-legacy-members
                            {file : Pad naar CSV bestand}
                            {--tenant=1 : Tenant ID}
                            {--date= : Referentiedatum in Y-m-d formaat}
                            {--delimiter=, : CSV delimiter}
                            {--update-existing : Bestaande matches bijwerken}
                            {--dry-run : Simuleer import zonder op te slaan}';

    protected $description = 'Importeer legacy members uit CSV, inclusief actieve abonnementen en abonnementen die minder dan 1 jaar vervallen zijn.';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');
        $updateExisting = (bool) $this->option('update-existing');
        $delimiter = (string) ($this->option('delimiter') ?: ',');
        $inputFile = (string) $this->argument('file');

        $referenceDate = $this->parseReferenceDate($this->option('date'));
        if (! $referenceDate) {
            $this->error('Ongeldige referentiedatum. Gebruik formaat Y-m-d.');
            return self::FAILURE;
        }

        $cutoffDate = $referenceDate->copy()->subYear()->startOfDay();

        $file = $this->resolveFilePath($inputFile);
        if (! $file) {
            $this->error('Bestand niet gevonden: ' . $inputFile);
            return self::FAILURE;
        }

        if (! is_readable($file)) {
            $this->error('Bestand is niet leesbaar: ' . $file);
            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        if (! $handle) {
            $this->error('Kon CSV bestand niet openen: ' . $file);
            return self::FAILURE;
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (! is_array($header) || empty($header)) {
            fclose($handle);
            $this->error('CSV header ontbreekt of kon niet gelezen worden.');
            return self::FAILURE;
        }

        $header = array_map(fn ($value) => $this->normalizeHeader($value), $header);

        $stats = [
            'rows' => 0,
            'included_found' => 0,
            'created' => 0,
            'updated' => 0,
            'expired_skipped' => 0,
            'duplicates_skipped' => 0,
            'invalid_skipped' => 0,
        ];

        $errors = [];
        $now = now();

        DB::beginTransaction();

        try {
            $rowNumber = 1;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;
                $stats['rows']++;

                try {
                    $normalized = $this->combineRow($header, $row);

                    $subscriptionEnd = $this->parseCsvDate($normalized['subscriptionend'] ?? null);
                    if (! $subscriptionEnd) {
                        $stats['invalid_skipped']++;
                        $errors[] = "Rij #{$rowNumber}: subscriptionend ontbreekt of is ongeldig.";
                        continue;
                    }

                    if ($subscriptionEnd->copy()->startOfDay()->lt($cutoffDate)) {
                        $stats['expired_skipped']++;
                        continue;
                    }

                    $stats['included_found']++;

                    $firstName = $this->cleanValue($normalized['firstname'] ?? null);
                    $lastName = $this->cleanValue($normalized['lastname'] ?? null);

                    if (! $firstName && ! $lastName) {
                        $stats['invalid_skipped']++;
                        $errors[] = "Rij #{$rowNumber}: firstname en lastname ontbreken.";
                        continue;
                    }

                    $membershipStart = $this->parseCsvDate($normalized['subscriptionstart'] ?? null)
                        ?? $subscriptionEnd->copy();

                    $payload = [
                        'tenant_id' => $tenantId,
                        'first_name' => $firstName ?: 'Onbekend',
                        'last_name' => $lastName ?: 'Onbekend',
                        'email' => $this->normalizeEmail($normalized['email'] ?? null),
                        'login' => null,
                        'password' => null,
                        'street' => $this->cleanValue($normalized['street'] ?? null),
                        'house_number' => null,
                        'box' => null,
                        'postal_code' => $this->cleanValue($normalized['postalcode'] ?? null),
                        'city' => $this->cleanValue($normalized['city'] ?? null),
                        'country' => 'BE',
                        'rfid_uid' => null,
                        'comment' => $this->buildComment($normalized),
                        'membership_starts_at' => $membershipStart->copy()->startOfDay()->toDateTimeString(),
                        'membership_ends_at' => $subscriptionEnd->copy()->startOfDay()->toDateTimeString(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $existing = $this->findExistingMember($payload);

                    if ($existing) {
                        if ($updateExisting) {
                            $existing->update([
                                'email' => $payload['email'],
                                'login' => $payload['login'],
                                'password' => $payload['password'],
                                'street' => $payload['street'],
                                'house_number' => $payload['house_number'],
                                'box' => $payload['box'],
                                'postal_code' => $payload['postal_code'],
                                'city' => $payload['city'],
                                'country' => $payload['country'],
                                'rfid_uid' => $payload['rfid_uid'],
                                'comment' => $payload['comment'],
                                'membership_starts_at' => $payload['membership_starts_at'],
                                'membership_ends_at' => $payload['membership_ends_at'],
                                'updated_at' => $now,
                            ]);

                            $stats['updated']++;
                        } else {
                            $stats['duplicates_skipped']++;
                        }

                        continue;
                    }

                    Member::query()->create($payload);
                    $stats['created']++;
                } catch (Throwable $e) {
                    $stats['invalid_skipped']++;
                    $errors[] = "Rij #{$rowNumber}: " . $e->getMessage();
                }
            }

            fclose($handle);

            if ($dryRun) {
                DB::rollBack();
                $this->info('Dry run voltooid. Er werd niets opgeslagen.');
            } else {
                DB::commit();
                $this->info('Import voltooid.');
            }

            $this->table(
                ['Metric', 'Waarde'],
                [
                    ['CSV rijen', $stats['rows']],
                    ['Members meegenomen', $stats['included_found']],
                    ['Aangemaakt', $stats['created']],
                    ['Bijgewerkt', $stats['updated']],
                    ['Te oud vervallen overgeslagen', $stats['expired_skipped']],
                    ['Duplicaten overgeslagen', $stats['duplicates_skipped']],
                    ['Ongeldige rijen overgeslagen', $stats['invalid_skipped']],
                    ['Referentiedatum', $referenceDate->toDateString()],
                    ['Cutoffdatum', $cutoffDate->toDateString()],
                    ['Tenant ID', $tenantId],
                    ['CSV pad', $file],
                ]
            );

            if (! empty($errors)) {
                $this->newLine();
                $this->warn('Eerste foutmeldingen:');
                foreach (array_slice($errors, 0, 15) as $error) {
                    $this->line(' - ' . $error);
                }

                $remaining = count($errors) - 15;
                if ($remaining > 0) {
                    $this->line(" - ... nog {$remaining} extra fout(en)");
                }
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            fclose($handle);
            DB::rollBack();

            $this->error('Import mislukt: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function resolveFilePath(string $input): ?string
    {
        $candidates = [];

        $trimmed = trim($input, "\"' \t\n\r\0\x0B");

        $candidates[] = $trimmed;

        if (preg_match('/^[a-zA-Z]:[\/\\\\]/', $trimmed)) {
            $candidates[] = $trimmed;
        } else {
            $candidates[] = base_path($trimmed);
            $candidates[] = storage_path('app/' . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $trimmed), DIRECTORY_SEPARATOR));

            $basename = basename(str_replace('\\', '/', $trimmed));
            $candidates[] = storage_path('app/import/' . $basename);
            $candidates[] = storage_path('app/imports/' . $basename);
        }

        foreach ($candidates as $candidate) {
            if ($candidate && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function parseReferenceDate(?string $date): ?Carbon
    {
        if (! $date) {
            return now()->startOfDay();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    protected function combineRow(array $header, array $row): array
    {
        $combined = [];

        foreach ($header as $index => $column) {
            $combined[$column] = array_key_exists($index, $row) ? $row[$index] : null;
        }

        return $combined;
    }

    protected function normalizeHeader(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    protected function cleanValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return $value;
    }

    protected function normalizeEmail(mixed $value): ?string
    {
        $email = $this->cleanValue($value);
        if (! $email) {
            return null;
        }

        $email = strtolower($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function parseCsvDate(mixed $value): ?Carbon
    {
        $value = $this->cleanValue($value);
        if (! $value) {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'd.m.Y',
            'Y/m/d',
            'm/d/Y',
            'm-d-Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (Throwable) {
            }
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function buildComment(array $normalized): ?string
    {
        $parts = [];

        $phone = $this->cleanValue($normalized['tel'] ?? $normalized['phone'] ?? null);
        $legacyId = $this->cleanValue($normalized['id'] ?? null);
        $comment = $this->cleanValue($normalized['comment'] ?? null);

        if ($phone) {
            $parts[] = 'Legacy tel: ' . $phone;
        }

        if ($comment) {
            $parts[] = $comment;
        }

        if ($legacyId) {
            $parts[] = 'Legacy member ID: ' . $legacyId;
        }

        if (empty($parts)) {
            return null;
        }

        return implode(PHP_EOL, $parts);
    }

    protected function findExistingMember(array $payload): ?Member
    {
        return Member::query()
            ->where('tenant_id', $payload['tenant_id'])
            ->where('first_name', $payload['first_name'])
            ->where('last_name', $payload['last_name'])
            ->where(function ($query) use ($payload) {
                if (! empty($payload['email'])) {
                    $query->where('email', $payload['email']);
                } else {
                    $query->whereNull('email');
                }
            })
            ->whereDate('membership_starts_at', substr($payload['membership_starts_at'], 0, 10))
            ->whereDate('membership_ends_at', substr($payload['membership_ends_at'], 0, 10))
            ->first();
    }
}
