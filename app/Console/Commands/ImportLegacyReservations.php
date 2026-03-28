<?php

namespace App\Console\Commands;

use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportLegacyReservations extends Command
{
    protected $signature = 'playdrive:import-legacy-reservations
                            {file : Pad naar het CSV-bestand}
                            {--tenant=1 : Tenant ID indien registrations tenant-based zijn}
                            {--truncate : Leeg eerst de registrations tabel}';

    protected $description = 'Importeert legacy reservaties uit oud CSV-bestand naar PlayDrive registrations';

    private const EXPECTED_COLUMN_COUNT = 30;

    private const HEADER = [
        'id',
        'createdbyuserid',
        'createdon',
        'updatedbyuserid',
        'updatedon',
        'amountadults',
        'amountstudents',
        'city',
        'comment',
        'date',
        'duration_hours',
        'duration_minutes',
        'duration_timeformatted',
        'name',
        'timeend_hours',
        'timeend_minutes',
        'timeend_timeformatted',
        'timestart_hours',
        'timestart_minutes',
        'timestart_timeformatted',
        'status',
        'byfacebook',
        'byfriends',
        'byfront',
        'byinstagram',
        'byinternet',
        'bynewspaper',
        'bysomethingelse',
        'tel',
        'email',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        $tenantId = (int) $this->option('tenant');
        $truncate = (bool) $this->option('truncate');

        if (! file_exists($file)) {
            $this->error("Bestand niet gevonden: {$file}");
            return self::FAILURE;
        }

        if ($truncate) {
            if (! $this->confirm('Weet je zeker dat je registrations wil leegmaken?', true)) {
                $this->warn('Import geannuleerd.');
                return self::INVALID;
            }

            Registration::query()->delete();
            $this->info('Registrations tabel leeggemaakt.');
        }

        $records = $this->readLogicalCsvRecords($file);

        if (empty($records)) {
            $this->warn('Geen records gevonden.');
            return self::SUCCESS;
        }

        $header = array_shift($records);

        if ($this->normalizeHeader($header) !== self::HEADER) {
            $this->warn('CSV header wijkt af van verwacht formaat. Import gaat toch verder.');
        }

        $hasTenantColumn = Schema::hasColumn('registrations', 'tenant_id');

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($records as $index => $rawRecord) {
            try {
                $row = $this->normalizeRow($rawRecord);

                if ($row === null) {
                    $skipped++;
                    $this->warn('Rij overgeslagen wegens ongeldig formaat op logisch record #' . ($index + 2));
                    continue;
                }

                $data = $this->mapLegacyRowToRegistration($row, $tenantId, $hasTenantColumn);

                Registration::create($data);
                $imported++;
            } catch (\Throwable $e) {
                $errors++;
                $this->error('Fout op logisch record #' . ($index + 2) . ': ' . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Import klaar.");
        $this->line("Geïmporteerd: {$imported}");
        $this->line("Overgeslagen: {$skipped}");
        $this->line("Fouten: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Leest het CSV-bestand logisch record per logisch record.
     * Dit vangt multiline comments op door lijnen te groeperen tot de volgende lijn die met "nummer;" begint.
     */
    private function readLogicalCsvRecords(string $file): array
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $records = [];

        $buffer = null;

        foreach ($lines as $lineNumber => $line) {
            $line = (string) $line;

            if ($lineNumber === 0) {
                $records[] = str_getcsv($line, ';', '"');
                continue;
            }

            if (preg_match('/^\d+;/', $line)) {
                if ($buffer !== null) {
                    $records[] = explode(';', $buffer);
                }

                $buffer = $line;
            } else {
                if ($buffer === null) {
                    continue;
                }

                $buffer .= "\n" . $line;
            }
        }

        if ($buffer !== null) {
            $records[] = explode(';', $buffer);
        }

        return $records;
    }

    /**
     * Corrigeert rows met te veel kolommen door alles tussen city en date als comment te behandelen.
     */
    private function normalizeRow(array $row): ?array
    {
        $row = array_map(fn ($value) => $this->cleanValue($value), $row);

        $count = count($row);

        if ($count === self::EXPECTED_COLUMN_COUNT) {
            return array_combine(self::HEADER, $row);
        }

        if ($count > self::EXPECTED_COLUMN_COUNT) {
            // Structuur:
            // 0..7 = vast
            // 8 = comment
            // 9..29 = trailing 21 velden
            $first = array_slice($row, 0, 8);
            $tail = array_slice($row, -21);
            $commentParts = array_slice($row, 8, $count - 8 - 21);
            $comment = implode(';', $commentParts);

            $normalized = [
                ...$first,
                $comment,
                ...$tail,
            ];

            if (count($normalized) === self::EXPECTED_COLUMN_COUNT) {
                return array_combine(self::HEADER, $normalized);
            }
        }

        return null;
    }

    private function normalizeHeader(array $header): array
    {
        return array_map(
            fn ($value) => trim(mb_strtolower((string) $value)),
            $header
        );
    }

    private function mapLegacyRowToRegistration(array $row, int $tenantId, bool $hasTenantColumn): array
    {
        $legacyName = trim((string) ($row['name'] ?? ''));
        $isBirthday = str_starts_with($legacyName, '***');

        $cleanName = ltrim($legacyName, '*');
        $cleanName = trim($cleanName);

        $eventDate = $this->parseDate($row['date'] ?? null);
        $eventTime = $this->buildTime($row['timestart_hours'] ?? null, $row['timestart_minutes'] ?? null);

        $checkedInAt = null;
        $checkedOutAt = null;

        $status = $this->mapStatus((string) ($row['status'] ?? ''));

        if ($status === Registration::STATUS_CHECKED_IN) {
            $checkedInAt = $this->parseDateTime(
                $eventDate,
                $this->buildTime($row['timestart_hours'] ?? null, $row['timestart_minutes'] ?? null)
            );
        }

        if (in_array($status, [Registration::STATUS_CHECKED_OUT, Registration::STATUS_PAID], true)) {
            $checkedInAt = $this->parseDateTime(
                $eventDate,
                $this->buildTime($row['timestart_hours'] ?? null, $row['timestart_minutes'] ?? null)
            );

            $checkedOutAt = $this->parseDateTime(
                $eventDate,
                $this->buildTime($row['timeend_hours'] ?? null, $row['timeend_minutes'] ?? null)
            );
        }

        $playedMinutes = $this->calculatePlayedMinutes(
            $row['duration_hours'] ?? null,
            $row['duration_minutes'] ?? null
        );

        $data = [
            'name' => $cleanName !== '' ? $cleanName : 'Onbekend',
            'phone' => $this->nullIfEmpty($row['tel'] ?? null),
            'email' => $this->nullIfEmpty($row['email'] ?? null),
            'postal_code' => null,
            'municipality' => $this->nullIfEmpty($row['city'] ?? null),
            'event_type_id' => $isBirthday ? 2 : 1,
            'event_date' => $eventDate,
            'event_time' => $eventTime,
            'stay_option_id' => null,
            'catering_option_id' => $isBirthday ? 4 : null,
            'participants_children' => (int) ($row['amountstudents'] ?? 0),
            'participants_adults' => (int) ($row['amountadults'] ?? 0),
            'participants_supervisors' => 0,
            'comment' => $this->nullIfEmpty($row['comment'] ?? null),
            'stats' => [
                'legacy_id' => $row['id'] ?? null,
                'legacy_status' => $row['status'] ?? null,
                'legacy_createdbyuserid' => $row['createdbyuserid'] ?? null,
                'legacy_updatedbyuserid' => $row['updatedbyuserid'] ?? null,
                'legacy_source' => $this->detectSource($row),
                'legacy_duration' => [
                    'hours' => (int) ($row['duration_hours'] ?? 0),
                    'minutes' => (int) ($row['duration_minutes'] ?? 0),
                    'formatted' => $row['duration_timeformatted'] ?? null,
                ],
            ],
            'status' => $status,
            'invoice_requested' => false,
            'invoice_company_name' => null,
            'invoice_vat_number' => null,
            'invoice_email' => null,
            'invoice_address' => null,
            'invoice_postal_code' => null,
            'invoice_city' => null,
            'checked_in_at' => $checkedInAt,
            'checked_out_at' => $checkedOutAt,
            'played_minutes' => $playedMinutes,
            'bill_total_cents' => 0,
            'outside_opening_hours' => false,
            'created_at' => $this->parseDateTimeString($row['createdon'] ?? null),
            'updated_at' => $this->parseDateTimeString($row['updatedon'] ?? null)
                ?? $this->parseDateTimeString($row['createdon'] ?? null),
        ];

        if ($hasTenantColumn) {
            $data['tenant_id'] = $tenantId;
        }

        return $data;
    }

    private function mapStatus(string $legacyStatus): string
    {
        return match ((int) $legacyStatus) {
            0 => Registration::STATUS_CONFIRMED,   // RESERVED
            1 => Registration::STATUS_CHECKED_IN,  // CHECKED_IN
            2 => Registration::STATUS_PAID,        // CHECKED_OUT => PAID
            3 => Registration::STATUS_NO_SHOW,     // NO_SHOW
            default => Registration::STATUS_NEW,
        };
    }

    private function detectSource(array $row): ?string
    {
        $sources = [];

        if ((int) ($row['byfacebook'] ?? 0) === 1) {
            $sources[] = 'facebook';
        }

        if ((int) ($row['byfriends'] ?? 0) === 1) {
            $sources[] = 'friends';
        }

        if ((int) ($row['byfront'] ?? 0) === 1) {
            $sources[] = 'front';
        }

        if ((int) ($row['byinstagram'] ?? 0) === 1) {
            $sources[] = 'instagram';
        }

        if ((int) ($row['byinternet'] ?? 0) === 1) {
            $sources[] = 'internet';
        }

        if ((int) ($row['bynewspaper'] ?? 0) === 1) {
            $sources[] = 'newspaper';
        }

        if ((int) ($row['bysomethingelse'] ?? 0) === 1) {
            $sources[] = 'something_else';
        }

        return empty($sources) ? null : implode(',', $sources);
    }

    private function calculatePlayedMinutes($hours, $minutes): int
    {
        return ((int) $hours * 60) + (int) $minutes;
    }

    private function buildTime($hours, $minutes): ?string
    {
        if ($hours === null || $minutes === null || $hours === '' || $minutes === '') {
            return null;
        }

        return sprintf('%02d:%02d', (int) $hours, (int) $minutes);
    }

    private function parseDate(?string $value): ?string
    {
        $value = $this->cleanValue($value);

        if (! $value || strtoupper($value) === 'NULL') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateTime(?string $date, ?string $time): ?string
    {
        if (! $date || ! $time) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse("{$date} {$time}")->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateTimeString(?string $value): ?string
    {
        $value = $this->cleanValue($value);

        if (! $value || strtoupper($value) === 'NULL') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return trim($value);
    }

    private function nullIfEmpty($value): ?string
    {
        $value = $this->cleanValue($value);

        return $value === '' ? null : $value;
    }
}
