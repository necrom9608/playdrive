<?php

namespace App\Domain\Pricing;

use App\Models\PricingProfile;
use Illuminate\Support\Collection;

class PricingEvaluationResult
{
    /**
     * @param  Collection<int, PricingEvaluationLine>  $lines
     * @param  array<int, array<string, mixed>>  $matchedRules
     */
    public function __construct(
        public readonly ?PricingProfile $profile,
        public readonly PricingContext $context,
        public readonly Collection $lines,
        public readonly array $matchedRules = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'profile' => $this->profile ? [
                'id' => $this->profile->id,
                'name' => $this->profile->name,
                'slug' => $this->profile->slug,
                'grace_minutes' => (int) $this->profile->grace_minutes,
                'extra_block_minutes' => (int) $this->profile->extra_block_minutes,
            ] : null,
            'context' => [
                'registration_id' => $this->context->registrationId,
                'played_minutes' => $this->context->playedMinutes,
                'children_count' => $this->context->childrenCount,
                'adults_count' => $this->context->adultsCount,
                'supervisors_count' => $this->context->supervisorsCount,
                'catering_option_id' => $this->context->cateringOptionId,
                'checked_in_at' => $this->context->checkedInAt?->toIso8601String(),
                'checked_out_at' => $this->context->checkedOutAt?->toIso8601String(),
            ],
            'matched_rules' => $this->matchedRules,
            'lines' => $this->lines->map(fn (PricingEvaluationLine $line) => $line->toArray())->values()->all(),
        ];
    }
}
