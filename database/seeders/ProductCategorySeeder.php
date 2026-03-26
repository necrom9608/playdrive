<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'game-inn')->firstOrFail();

        $items = [
            [
                'name' => 'Toegang',
                'slug' => 'toegang',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Cadeaubonnen',
                'slug' => 'cadeaubonnen',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Snacks',
                'slug' => 'snacks',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Drank',
                'slug' => 'drank',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            ProductCategory::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'slug' => $item['slug'],
                ],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => $item['is_active'],
                ]
            );
        }
    }
}
