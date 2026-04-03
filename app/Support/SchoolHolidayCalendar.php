<?php

namespace App\Support;

use Carbon\Carbon;

class SchoolHolidayCalendar
{
    public static function all(): array
    {
        return [
            2022 => [
                'autumn' => ['label' => 'Herfstvakantie', 'start' => '2022-10-31', 'end' => '2022-11-06'],
                'christmas' => ['label' => 'Kerstvakantie', 'start' => '2022-12-26', 'end' => '2023-01-08'],
            ],
            2023 => [
                'carnival' => ['label' => 'Krokusvakantie', 'start' => '2023-02-20', 'end' => '2023-02-26'],
                'easter' => ['label' => 'Paasvakantie', 'start' => '2023-04-03', 'end' => '2023-04-16'],
                'summer' => ['label' => 'Zomervakantie', 'start' => '2023-07-01', 'end' => '2023-08-31'],
                'autumn' => ['label' => 'Herfstvakantie', 'start' => '2023-10-30', 'end' => '2023-11-05'],
                'christmas' => ['label' => 'Kerstvakantie', 'start' => '2023-12-25', 'end' => '2024-01-07'],
            ],
            2024 => [
                'carnival' => ['label' => 'Krokusvakantie', 'start' => '2024-02-12', 'end' => '2024-02-18'],
                'easter' => ['label' => 'Paasvakantie', 'start' => '2024-04-01', 'end' => '2024-04-14'],
                'summer' => ['label' => 'Zomervakantie', 'start' => '2024-07-01', 'end' => '2024-08-31'],
                'autumn' => ['label' => 'Herfstvakantie', 'start' => '2024-10-28', 'end' => '2024-11-03'],
                'christmas' => ['label' => 'Kerstvakantie', 'start' => '2024-12-23', 'end' => '2025-01-05'],
            ],
            2025 => [
                'carnival' => ['label' => 'Krokusvakantie', 'start' => '2025-03-03', 'end' => '2025-03-09'],
                'easter' => ['label' => 'Paasvakantie', 'start' => '2025-04-07', 'end' => '2025-04-21'],
                'summer' => ['label' => 'Zomervakantie', 'start' => '2025-07-01', 'end' => '2025-08-31'],
                'autumn' => ['label' => 'Herfstvakantie', 'start' => '2025-10-27', 'end' => '2025-11-02'],
                'christmas' => ['label' => 'Kerstvakantie', 'start' => '2025-12-22', 'end' => '2026-01-04'],
            ],
            2026 => [
                'carnival' => ['label' => 'Krokusvakantie', 'start' => '2026-02-16', 'end' => '2026-02-22'],
                'easter' => ['label' => 'Paasvakantie', 'start' => '2026-04-06', 'end' => '2026-04-19'],
                'summer' => ['label' => 'Zomervakantie', 'start' => '2026-07-01', 'end' => '2026-08-31'],
                'autumn' => ['label' => 'Herfstvakantie', 'start' => '2026-11-02', 'end' => '2026-11-08'],
                'christmas' => ['label' => 'Kerstvakantie', 'start' => '2026-12-21', 'end' => '2027-01-03'],
            ],
            2027 => [
                'carnival' => ['label' => 'Krokusvakantie', 'start' => '2027-02-08', 'end' => '2027-02-14'],
                'easter' => ['label' => 'Paasvakantie', 'start' => '2027-03-29', 'end' => '2027-04-11'],
                'summer' => ['label' => 'Zomervakantie', 'start' => '2027-07-01', 'end' => '2027-08-31'],
                'autumn' => ['label' => 'Herfstvakantie', 'start' => '2027-11-01', 'end' => '2027-11-07'],
                'christmas' => ['label' => 'Kerstvakantie', 'start' => '2027-12-27', 'end' => '2028-01-09'],
            ],
        ];
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $year => $holidays) {
            foreach ($holidays as $key => $holiday) {
                $options[] = [
                    'key' => $key,
                    'year' => $year,
                    'label' => sprintf('%s %d', $holiday['label'], $year),
                    'start' => $holiday['start'],
                    'end' => $holiday['end'],
                    'days' => Carbon::parse($holiday['start'])->diffInDays(Carbon::parse($holiday['end'])) + 1,
                ];
            }
        }

        return $options;
    }

    public static function find(string $key, int $year): ?array
    {
        $holiday = self::all()[$year][$key] ?? null;

        if (! $holiday) {
            return null;
        }

        return [
            'key' => $key,
            'year' => $year,
            'label' => sprintf('%s %d', $holiday['label'], $year),
            'start' => $holiday['start'],
            'end' => $holiday['end'],
            'days' => Carbon::parse($holiday['start'])->diffInDays(Carbon::parse($holiday['end'])) + 1,
        ];
    }
}
