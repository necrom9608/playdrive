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
    ) {
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
        );
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
}
