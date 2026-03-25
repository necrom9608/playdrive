<?php

namespace App\Domain\Catalog;

use Illuminate\Support\Str;

class CatalogSlugService
{
    public function makeSlug(string $value): string
    {
        return Str::slug($value);
    }
}
