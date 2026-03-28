<?php

namespace App\Domain\Pricing;

class PricingEvaluationLine
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly string $source,
        public readonly ?int $ruleId = null,
        public readonly array $meta = [],
    ) {
    }

    public function withQuantity(int $quantity): self
    {
        return new self(
            productId: $this->productId,
            quantity: $quantity,
            source: $this->source,
            ruleId: $this->ruleId,
            meta: $this->meta,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'source' => $this->source,
            'rule_id' => $this->ruleId,
            'meta' => $this->meta,
        ];
    }
}
