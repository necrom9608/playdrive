<?php

namespace Database\Seeders;

use App\Models\CateringOption;
use Illuminate\Database\Seeder;

class CateringOptionSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Geen', 'code' => 'none', 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Drank', 'code' => 'drinks', 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Snacks', 'code' => 'snacks', 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Pannenkoeken', 'code' => 'pancakes', 'sort_order' => 4, 'is_active' => true],
            ['name' => 'Pizza', 'code' => 'pizza', 'sort_order' => 5, 'is_active' => true],
            ['name' => 'Aangepast', 'code' => 'custom', 'sort_order' => 99, 'is_active' => true],
        ];

        foreach ($items as $item) {
            CateringOption::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}
