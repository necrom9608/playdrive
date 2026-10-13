<?php

namespace App\Domain\Accounting;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Een boekhoudperiode (maand, kwartaal of vrije range) met begin- en einddatum.
 */
final class AccountingPeriod
{
    public const TYPE_MONTH = 'month';
    public const TYPE_QUARTER = 'quarter';
    public const TYPE_RANGE = 'range';

    private const MONTHS = [
        1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april', 5 => 'mei', 6 => 'juni',
        7 => 'juli', 8 => 'augustus', 9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
    ];

    public function __construct(
        public readonly string $type,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
    }

    public static function month(int $year, int $month): self
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();

        return new self(self::TYPE_MONTH, $start, $start->endOfMonth()->endOfDay());
    }

    public static function quarter(int $year, int $quarter): self
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('Kwartaal moet tussen 1 en 4 liggen.');
        }

        $start = CarbonImmutable::create($year, (($quarter - 1) * 3) + 1, 1)->startOfDay();

        return new self(self::TYPE_QUARTER, $start, $start->addMonths(2)->endOfMonth()->endOfDay());
    }

    public static function range(string $start, string $end): self
    {
        $startDate = CarbonImmutable::parse($start)->startOfDay();
        $endDate = CarbonImmutable::parse($end)->endOfDay();

        if ($endDate->lessThan($startDate)) {
            throw new InvalidArgumentException('De einddatum ligt voor de startdatum.');
        }

        return new self(self::TYPE_RANGE, $startDate, $endDate);
    }

    /**
     * Zelfde parameters als het Dagtotalen-scherm: mode, year, month, quarter, start, end.
     */
    public static function fromInput(array $input): self
    {
        $mode = $input['mode'] ?? self::TYPE_MONTH;
        $now = CarbonImmutable::now();
        $year = (int) ($input['year'] ?? $now->year);

        if ($mode === self::TYPE_QUARTER) {
            return self::quarter($year, (int) ($input['quarter'] ?? 1));
        }

        if ($mode === self::TYPE_RANGE && ! empty($input['start']) && ! empty($input['end'])) {
            return self::range((string) $input['start'], (string) $input['end']);
        }

        return self::month($year, (int) ($input['month'] ?? $now->month));
    }

    /**
     * De laatste volledig afgelopen periode op datum $date (vorige maand of vorig kwartaal).
     */
    public static function lastCompleted(string $frequency, CarbonImmutable $date): self
    {
        if ($frequency === self::TYPE_MONTH) {
            $previous = $date->startOfMonth()->subMonth();

            return self::month($previous->year, $previous->month);
        }

        $currentQuarterStart = $date->firstOfQuarter();
        $previous = $currentQuarterStart->subMonths(3);

        return self::quarter($previous->year, $previous->quarter);
    }

    /**
     * De eerstvolgende periode na deze (zelfde type; enkel voor maand/kwartaal).
     */
    public function next(): self
    {
        $nextStart = $this->end->addDay()->startOfDay();

        return $this->type === self::TYPE_MONTH
            ? self::month($nextStart->year, $nextStart->month)
            : self::quarter($nextStart->year, $nextStart->quarter);
    }

    /**
     * Datum waarop deze periode automatisch verstuurd wordt: dag $sendDay van de maand na het einde.
     */
    public function sendDate(int $sendDay): CarbonImmutable
    {
        $sendDay = max(1, min(28, $sendDay));

        return $this->end->addDay()->startOfMonth()->setDay($sendDay)->startOfDay();
    }

    public function label(): string
    {
        return match ($this->type) {
            self::TYPE_MONTH => ucfirst(self::MONTHS[$this->start->month]) . ' ' . $this->start->year,
            self::TYPE_QUARTER => 'Q' . $this->start->quarter . ' ' . $this->start->year,
            default => $this->start->format('d/m/Y') . ' – ' . $this->end->format('d/m/Y'),
        };
    }

    public function filenameSlug(): string
    {
        return match ($this->type) {
            self::TYPE_MONTH => $this->start->format('Y-m'),
            self::TYPE_QUARTER => $this->start->year . '-Q' . $this->start->quarter,
            default => $this->start->format('Ymd') . '_' . $this->end->format('Ymd'),
        };
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'label' => $this->label(),
        ];
    }
}
