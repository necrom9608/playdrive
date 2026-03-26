<?php

namespace Database\Seeders;

use App\Models\CateringOption;
use Illuminate\Database\Seeder;

class CateringOptionSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Geen', 'code' => 'none', 'emoji' => '🚫', 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Drank', 'code' => 'drinks', 'emoji' => '🥤', 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Snacks', 'code' => 'snacks', 'emoji' => '🍿', 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Pannenkoeken', 'code' => 'pancakes', 'emoji' => '🥞', 'sort_order' => 4, 'is_active' => true],
            ['name' => 'Pizza', 'code' => 'pizza', 'emoji' => '🍕', 'sort_order' => 5, 'is_active' => true],
            ['name' => 'Aangepast', 'code' => 'custom', 'emoji' => '🍽️', 'sort_order' => 99, 'is_active' => true],
        ];

        foreach ($items as $item) {
            CateringOption::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}
