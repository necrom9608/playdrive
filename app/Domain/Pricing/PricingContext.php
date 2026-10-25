<?php

namespace App\Domain\Pricing;

use App\Models\Registration;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class PricingContext
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?int $registrationId,
        public readonly int $childrenCount,
        public readonly int $adultsCount,
        public readonly int $supervisorsCount,
        public readonly ?int $cateringOptionId,
        public readonly ?CarbonInterface $checkedInAt,
        public readonly ?CarbonInterface $checkedOutAt,
        public readonly int $playedMinutes,
        public readonly int $childrenSubscribers = 0,
        public readonly int $adultsSubscribers = 0,
        /**
         * Speeltijd-segmenten voor vroegtijdig stoppen:
         * [['children' => betalend, 'adults' => betalend, 'minutes' => int, 'stopped_at' => ?string], ...]
         * Leeg = één segment voor de hele groep (payingCountForScope + playedMinutes).
         */
        public readonly array $earlyStopSegments = [],
    ) {
    }

    /**
     * Segmenten waarop speeltijd berekend wordt: de groep die blijft spelen
     * (volledige tijd) en per vroegtijdige stop een segment met eigen speeltijd.
     *
     * @return array<int, array{children: int, adults: int, minutes: int, stopped_at: ?string}>
     */
    public function durationSegments(): array
    {
        if ($this->earlyStopSegments === []) {
            return [[
                'children'   => $this->payingCountForScope('children'),
                'adults'     => $this->payingCountForScope('adults'),
                'minutes'    => $this->playedMinutes,
                'stopped_at' => null,
            ]];
        }

        $stoppedChildren = 0;
        $stoppedAdults = 0;
        $stoppedChildSubs = 0;
        $stoppedAdultSubs = 0;

        foreach ($this->earlyStopSegments as $segment) {
            $stoppedChildren += $segment['children_total'];
            $stoppedAdults += $segment['adults_total'];
            $stoppedChildSubs += $segment['children_subscribers'];
            $stoppedAdultSubs += $segment['adults_subscribers'];
        }

        $remainingChildren = max(0, $this->childrenCount - $stoppedChildren);
        $remainingAdults = max(0, $this->adultsCount - $stoppedAdults);

        $segments = [[
            'children'   => max(0, $remainingChildren - max(0, $this->childrenSubscribers - $stoppedChildSubs)),
            'adults'     => max(0, $remainingAdults - max(0, $this->adultsSubscribers - $stoppedAdultSubs)),
            'minutes'    => $this->playedMinutes,
            'stopped_at' => null,
        ]];

        foreach ($this->earlyStopSegments as $segment) {
            $segments[] = [
                'children'   => max(0, $segment['children_total'] - $segment['children_subscribers']),
                'adults'     => max(0, $segment['adults_total'] - $segment['adults_subscribers']),
                'minutes'    => $segment['minutes'],
                'stopped_at' => $segment['stopped_at'],
            ];
        }

        return $segments;
    }

    public static function fromRegistration(Registration $registration, ?string $checkedOutAt = null): self
    {
        $checkedInAt = $registration->checked_in_at;
        $effectiveCheckedOutAt = $checkedOutAt
            ? Carbon::parse($checkedOutAt)
            : ($registration->checked_out_at ?: now());

        $playedMinutes = self::calculatePlayedMinutes($checkedInAt, $effectiveCheckedOutAt, $registration->played_minutes);

        return new self(
            tenantId: (int) $registration->tenant_id,
            registrationId: (int) $registration->id,
            childrenCount: max(0, (int) ($registration->participants_children ?? 0)),
            adultsCount: max(0, (int) ($registration->participants_adults ?? 0)),
            supervisorsCount: max(0, (int) ($registration->participants_supervisors ?? 0)),
            cateringOptionId: $registration->catering_option_id ? (int) $registration->catering_option_id : null,
            checkedInAt: $checkedInAt,
            checkedOutAt: $effectiveCheckedOutAt,
            playedMinutes: $playedMinutes,
            childrenSubscribers: $registration->subscriberCountForScope('children'),
            adultsSubscribers: $registration->subscriberCountForScope('adults'),
            earlyStopSegments: self::earlyStopSegmentsFor($registration, $checkedInAt, $effectiveCheckedOutAt, $playedMinutes),
        );
    }

    /**
     * Vroegtijdige stops omzetten naar segmenten met eigen speeltijd
     * (van inchecken tot stoppen, nooit langer dan de groep).
     */
    private static function earlyStopSegmentsFor(
        Registration $registration,
        ?CarbonInterface $checkedInAt,
        ?CarbonInterface $checkedOutAt,
        int $groupMinutes,
    ): array {
        $stops = $registration->relationLoaded('earlyStops')
            ? $registration->earlyStops
            : $registration->earlyStops()->with('subscriber')->get();

        return $stops
            ->map(function ($stop) use ($checkedInAt, $checkedOutAt, $groupMinutes) {
                $stoppedAt = $stop->stopped_at;

                if ($checkedOutAt && $stoppedAt && $stoppedAt->greaterThan($checkedOutAt)) {
                    $stoppedAt = $checkedOutAt;
                }

                $minutes = min($groupMinutes, self::calculatePlayedMinutes($checkedInAt, $stoppedAt, 0));
                $scope = $stop->subscriber?->participant_scope;

                return [
                    'children_total'       => (int) $stop->participants_children,
                    'adults_total'         => (int) $stop->participants_adults,
                    'children_subscribers' => $scope === 'children' ? 1 : 0,
                    'adults_subscribers'   => $scope === 'adults' ? 1 : 0,
                    'minutes'              => max(0, $minutes),
                    'stopped_at'           => $stoppedAt?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    public static function calculatePlayedMinutes(?CarbonInterface $checkedInAt, ?CarbonInterface $checkedOutAt, ?int $fallback = null): int
    {
        if ($checkedInAt && $checkedOutAt) {
            $seconds = $checkedOutAt->getTimestamp() - $checkedInAt->getTimestamp();

            if ($seconds > 0) {
                return (int) ceil($seconds / 60);
            }
        }

        return max(0, (int) ($fallback ?? 0));
    }

    public function participantCountForScope(?string $scope): int
    {
        return match ($scope) {
            'children', 'child', 'kids', 'students', 'child_student' => $this->childrenCount,
            'adults', 'adult' => $this->adultsCount,
            'all' => $this->childrenCount + $this->adultsCount,
            default => 0,
        };
    }

    /**
     * Aantal betalende deelnemers voor speeltijd: deelnemers min abonnees.
     * Catering gebruikt participantCountForScope (abonnees betalen catering wel).
     */
    public function payingCountForScope(?string $scope): int
    {
        $children = max(0, $this->childrenCount - $this->childrenSubscribers);
        $adults   = max(0, $this->adultsCount - $this->adultsSubscribers);

        return match ($scope) {
            'children', 'child', 'kids', 'students', 'child_student' => $children,
            'adults', 'adult' => $adults,
            'all' => $children + $adults,
            default => 0,
        };
    }
}
