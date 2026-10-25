<?php

namespace App\Domain\Pricing;

use App\Models\PricingProfile;
use App\Models\PricingRule;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PricingEvaluator
{
    public function evaluate(PricingContext $context): PricingEvaluationResult
    {
        $profile = $this->resolveProfile($context->tenantId);

        if (! $profile) {
            return new PricingEvaluationResult(
                profile: null,
                context: $context,
                lines: collect(),
                matchedRules: [],
            );
        }

        $rules = $profile->rules
            ->where('is_active', true)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();

        $matchedRules = [];
        $lines = collect();

        // Speeltijd per segment: de groep (volledige tijd) en elke vroegtijdige
        // stop (eigen speeltijd). Abonnees betalen niet mee.
        foreach ($context->durationSegments() as $segment) {
            foreach (['children', 'adults'] as $scope) {
                $count = (int) $segment[$scope];

                if ($count <= 0) {
                    continue;
                }

                $minutes = (int) $segment['minutes'];
                $rule = $this->findMatchingDurationRule($rules, $scope, $minutes);

                // "Doorschuiven naar volgend tarief": het volgende tarief van
                // dezelfde doelgroep aanrekenen (bv. 1u22 → tarief 2u).
                $rule = $rule ? $this->resolveNextRuleChain($rules, $rule, $scope) : null;

                if (! $rule) {
                    Log::warning('Pricing: geen speeltijdtarief gevonden', [
                        'registration_id' => $context->registrationId,
                        'scope'           => $scope,
                        'count'           => $count,
                        'minutes'         => $minutes,
                    ]);

                    continue;
                }

                $ruleLines = $this->expandRuleToLines($rule, $context, $count, 'duration', $minutes);

                if ($ruleLines->isEmpty()) {
                    Log::warning('Pricing: speeltijdregel zonder product', [
                        'registration_id' => $context->registrationId,
                        'rule_id'         => $rule->id,
                        'scope'           => $scope,
                        'count'           => $count,
                    ]);
                }

                $matchedRules[] = $this->mapMatchedRule($rule, 'duration', [
                    'participant_scope' => $scope,
                    'minutes'           => $minutes,
                    'stopped_at'        => $segment['stopped_at'],
                ]);
                $lines = $lines->merge($ruleLines);
            }
        }

        $cateringRules = $rules
            ->filter(fn (PricingRule $rule) => $rule->type === PricingRule::TYPE_CATERING)
            ->filter(fn (PricingRule $rule) => $this->matchesCateringRule($rule, $context))
            ->values();

        foreach ($cateringRules as $rule) {
            $scope = $this->ruleScope($rule);
            $count = $context->participantCountForScope($scope);

            if ($count <= 0) {
                continue;
            }

            $matchedRules[] = $this->mapMatchedRule($rule, 'catering', ['participant_scope' => $scope]);
            $lines = $lines->merge($this->expandRuleToLines($rule, $context, $count, 'catering'));
        }

        $mergedLines = $lines
            ->groupBy(fn (PricingEvaluationLine $line) => $line->productId)
            ->map(function (Collection $group) {
                /** @var PricingEvaluationLine $first */
                $first = $group->first();

                return $first->withQuantity((int) $group->sum(fn (PricingEvaluationLine $line) => $line->quantity));
            })
            ->values();

        return new PricingEvaluationResult(
            profile: $profile,
            context: $context,
            lines: $mergedLines,
            matchedRules: $matchedRules,
        );
    }

    private function resolveProfile(int $tenantId): ?PricingProfile
    {
        return PricingProfile::query()
            ->with(['rules'])
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    private function findMatchingDurationRule(Collection $rules, string $scope, int $minutes): ?PricingRule
    {
        return $rules
            ->filter(fn (PricingRule $rule) => $rule->type === PricingRule::TYPE_DURATION)
            ->first(function (PricingRule $rule) use ($scope, $minutes) {
                $conditions = (array) ($rule->conditions ?? []);
                $ruleScope = $this->normalizeScope($conditions['participant_scope'] ?? $conditions['audience'] ?? null);

                if ($ruleScope !== $scope && $ruleScope !== 'all') {
                    return false;
                }

                $from = $conditions['from_minutes'] ?? $conditions['min_minutes'] ?? null;
                $until = $conditions['until_minutes'] ?? $conditions['max_minutes'] ?? null;

                if ($from !== null && $minutes < (int) $from) {
                    return false;
                }

                if ($until !== null && $minutes > (int) $until) {
                    return false;
                }

                return true;
            });
    }

    /**
     * Volg "doorschuiven naar volgend tarief" tot een regel met een product.
     * Volgend tarief = duurregel van dezelfde doelgroep (of iedereen) met de
     * kleinste "van minuut" groter dan die van de huidige regel.
     */
    private function resolveNextRuleChain(Collection $rules, PricingRule $rule, string $scope): ?PricingRule
    {
        $guard = 0;

        while ((((array) ($rule->actions ?? []))['billing_mode'] ?? 'fixed_product') === 'next_rule' && $guard++ < 10) {
            $currentFrom = (int) (((array) ($rule->conditions ?? []))['from_minutes'] ?? 0);

            $next = $rules
                ->filter(fn (PricingRule $candidate) => $candidate->type === PricingRule::TYPE_DURATION)
                ->filter(function (PricingRule $candidate) use ($scope, $currentFrom, $rule) {
                    if ((int) $candidate->id === (int) $rule->id) {
                        return false;
                    }

                    $conditions = (array) ($candidate->conditions ?? []);
                    $candidateScope = $this->normalizeScope($conditions['participant_scope'] ?? $conditions['audience'] ?? null);

                    if ($candidateScope !== $scope && $candidateScope !== 'all') {
                        return false;
                    }

                    return (int) ($conditions['from_minutes'] ?? 0) > $currentFrom;
                })
                ->sortBy(fn (PricingRule $candidate) => (int) (((array) ($candidate->conditions ?? []))['from_minutes'] ?? 0))
                ->first();

            if (! $next) {
                return null;
            }

            $rule = $next;
        }

        return $rule;
    }

    private function matchesCateringRule(PricingRule $rule, PricingContext $context): bool
    {
        $conditions = (array) ($rule->conditions ?? []);
        $requiredOptionId = $conditions['catering_option_id'] ?? null;

        if ((int) ($requiredOptionId ?? 0) <= 0) {
            return false;
        }

        if ((int) $requiredOptionId !== (int) ($context->cateringOptionId ?? 0)) {
            return false;
        }

        $scope = $this->ruleScope($rule);

        return $context->participantCountForScope($scope) > 0;
    }

    /**
     * @return Collection<int, PricingEvaluationLine>
     */
    private function expandRuleToLines(PricingRule $rule, PricingContext $context, int $participantCount, string $source, ?int $minutes = null): Collection
    {
        $actions = (array) ($rule->actions ?? []);

        if (isset($actions['add_products']) && is_array($actions['add_products'])) {
            return collect($actions['add_products'])
                ->map(function ($item) use ($rule, $participantCount, $source) {
                    $productId = (int) ($item['product_id'] ?? 0);

                    if ($productId <= 0) {
                        return null;
                    }

                    $quantity = $this->resolveQuantity($item['quantity_mode'] ?? 'per_person', $participantCount, $item['quantity'] ?? null, $item['quantity_per_person'] ?? null);

                    if ($quantity <= 0) {
                        return null;
                    }

                    return new PricingEvaluationLine(
                        productId: $productId,
                        quantity: $quantity,
                        source: $source,
                        ruleId: (int) $rule->id,
                        meta: [
                            'rule_name' => $rule->name,
                            'rule_type' => $rule->type,
                        ],
                    );
                })
                ->filter()
                ->values();
        }

        $productId = (int) ($actions['product_id'] ?? 0);
        $extraProductId = (int) ($actions['extra_product_id'] ?? 0);
        $billingMode = (string) ($actions['billing_mode'] ?? 'fixed_product');
        $extraThresholdMinutes = (int) ($actions['extra_threshold_minutes'] ?? 0);

        $lines = collect();

        if ($productId > 0 && $billingMode !== 'next_rule') {
            $lines->push(new PricingEvaluationLine(
                productId: $productId,
                quantity: $participantCount,
                source: $source,
                ruleId: (int) $rule->id,
                meta: [
                    'rule_name' => $rule->name,
                    'rule_type' => $rule->type,
                    'billing_mode' => $billingMode,
                ],
            ));
        }

        if ($billingMode === 'product_plus_extra' && $extraProductId > 0) {
            $lines->push(new PricingEvaluationLine(
                productId: $extraProductId,
                quantity: $participantCount,
                source: $source,
                ruleId: (int) $rule->id,
                meta: [
                    'rule_name' => $rule->name,
                    'rule_type' => $rule->type,
                    'extra_threshold_minutes' => $extraThresholdMinutes,
                ],
            ));
        }

        return $lines;
    }

    private function resolveQuantity(string $quantityMode, int $participantCount, mixed $quantity = null, mixed $quantityPerPerson = null): int
    {
        return match ($quantityMode) {
            'fixed' => max(0, (int) ($quantity ?? 1)),
            'multiplier' => (int) round($participantCount * (float) ($quantityPerPerson ?? 1)),
            'per_person' => $participantCount,
            default => $participantCount,
        };
    }

    private function ruleScope(PricingRule $rule): string
    {
        $conditions = (array) ($rule->conditions ?? []);

        return $this->normalizeScope($conditions['participant_scope'] ?? $conditions['audience'] ?? 'all');
    }

    private function normalizeScope(?string $scope): string
    {
        return match ($scope) {
            'child', 'kids', 'students', 'child_student' => 'children',
            'adult' => 'adults',
            'all', 'children', 'adults' => $scope,
            default => 'all',
        };
    }

    private function mapMatchedRule(PricingRule $rule, string $source, array $extra = []): array
    {
        return [
            'id' => (int) $rule->id,
            'type' => $rule->type,
            'name' => $rule->name,
            'source' => $source,
            'conditions' => $rule->conditions ?? [],
            'actions' => $rule->actions ?? [],
            ...$extra,
        ];
    }

    public function enrichWithProducts(PricingEvaluationResult $result): array
    {
        $productIds = collect($result->lines)
            ->pluck('productId')
            ->unique()
            ->values();

        $products = Product::query()
            ->where('tenant_id', $result->context->tenantId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $payload = $result->toArray();
        $payload['lines'] = collect($payload['lines'])
            ->map(function (array $line) use ($products) {
                /** @var Product|null $product */
                $product = $products->get($line['product_id']);

                return [
                    ...$line,
                    'product' => $product ? [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price_excl_vat' => (float) $product->price_excl_vat,
                        'price_incl_vat' => (float) $product->price_incl_vat,
                        'vat_rate' => (float) $product->vat_rate,
                    ] : null,
                ];
            })
            ->values()
            ->all();

        $payload['totals'] = [
            'subtotal_excl_vat' => round((float) collect($payload['lines'])->sum(fn (array $line) => ((float) data_get($line, 'product.price_excl_vat', 0)) * (int) $line['quantity']), 2),
            'total_incl_vat' => round((float) collect($payload['lines'])->sum(fn (array $line) => ((float) data_get($line, 'product.price_incl_vat', 0)) * (int) $line['quantity']), 2),
        ];

        return $payload;
    }
}
