<?php

namespace App\Domain\Display;

use App\Models\OpeningException;
use App\Models\OpeningHour;
use App\Models\RegionSeason;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * Openingsuren van één dag, zoals in Backoffice → Openingsuren ingesteld:
 *   1. een uitzondering op die datum wint altijd;
 *   2. anders het seizoen van de regio op die datum (hoogste prioriteit), met de uren van die weekdag;
 *   3. geen uren voor dat seizoen → de gewone uren ('regular').
 */
class OpeningHoursToday
{
    private const REGULAR = 'regular';

    public function __construct(private readonly Tenant $tenant)
    {
    }

    /** @return array{date:string, weekday:int, is_open:bool, from:?string, until:?string, label:?string} */
    public function forDate(CarbonImmutable $date): array
    {
        $day = $date->toDateString();
        $base = ['date' => $day, 'weekday' => $date->dayOfWeekIso, 'label' => null];

        $exception = OpeningException::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereDate('date', $day)
            ->first();

        if ($exception) {
            return [...$base, ...$this->times($exception->is_open, $exception->open_from, $exception->open_until),
                'label' => $exception->label ?: null];
        }

        $seasonKey = self::REGULAR;
        if ($this->tenant->region_code) {
            $seasonKey = RegionSeason::query()
                ->where('region_code', $this->tenant->region_code)
                ->whereDate('date_from', '<=', $day)
                ->whereDate('date_until', '>=', $day)
                ->orderByDesc('priority')
                ->value('season_key') ?: self::REGULAR;
        }

        $hour = $this->hourFor($seasonKey, $date->dayOfWeekIso);
        if (! $hour && $seasonKey !== self::REGULAR) {
            $hour = $this->hourFor(self::REGULAR, $date->dayOfWeekIso);
        }

        if (! $hour) {
            return [...$base, 'is_open' => false, 'from' => null, 'until' => null];
        }

        return [...$base, ...$this->times($hour->is_open, $hour->open_from, $hour->open_until)];
    }

    /** Vandaag + de eerstvolgende open dag daarna (max. 3 weken vooruit). */
    public function summary(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $this->forDate($now->startOfDay());

        $next = null;
        for ($i = 1; $i <= 21; $i++) {
            $day = $this->forDate($now->startOfDay()->addDays($i));
            if ($day['is_open']) {
                $next = $day;
                break;
            }
        }

        return ['today' => $today, 'next' => $next];
    }

    private function hourFor(string $seasonKey, int $weekday): ?OpeningHour
    {
        return OpeningHour::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('season_key', $seasonKey)
            ->where('weekday', $weekday)
            ->first();
    }

    private function times(bool $isOpen, $from, $until): array
    {
        $from = $this->hm($from);
        $until = $this->hm($until);

        return [
            'is_open' => $isOpen && $from !== null && $until !== null,
            'from'    => $from,
            'until'   => $until,
        ];
    }

    private function hm($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_match('/^(\d{1,2}):(\d{2})/', (string) $value, $m)
            ? str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2]
            : null;
    }
}
