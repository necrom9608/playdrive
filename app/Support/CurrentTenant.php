<?php

namespace App\Support;

use App\Models\Tenant;

class CurrentTenant
{
    public function __construct(
        public readonly ?Tenant $tenant
    ) {
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function exists(): bool
    {
        return $this->tenant !== null;
    }
}
