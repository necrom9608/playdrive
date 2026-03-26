<?php

namespace Database\Seeders;

use App\Models\StayOption;
use Illuminate\Database\Seeder;

class StayOptionSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => '1 uur', 'code' => '1h', 'emoji' => '⏱️', 'duration_minutes' => 60, 'sort_order' => 1, 'is_active' => true],
            ['name' => '2 uur', 'code' => '2h', 'emoji' => '🕑', 'duration_minutes' => 120, 'sort_order' => 2, 'is_active' => true],
            ['name' => '3 uur', 'code' => '3h', 'emoji' => '🕒', 'duration_minutes' => 180, 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Halve dag', 'code' => 'half_day', 'emoji' => '🌤️', 'duration_minutes' => 240, 'sort_order' => 4, 'is_active' => true],
            ['name' => 'Volledige dag', 'code' => 'full_day', 'emoji' => '🌞', 'duration_minutes' => 480, 'sort_order' => 5, 'is_active' => true],
        ];

        foreach ($items as $item) {
            StayOption::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}
