<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Vrij spelen', 'code' => 'free_play', 'emoji' => '🎮', 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Verjaardag', 'code' => 'birthday', 'emoji' => '🎂', 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Groepsbezoek', 'code' => 'group_visit', 'emoji' => '👥', 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Teambuilding', 'code' => 'team_building', 'emoji' => '🤝', 'sort_order' => 4, 'is_active' => true],
            ['name' => 'School', 'code' => 'school', 'emoji' => '🎓', 'sort_order' => 5, 'is_active' => true],
            ['name' => 'Andere', 'code' => 'other', 'emoji' => '❓', 'sort_order' => 99, 'is_active' => true],
        ];

        foreach ($items as $item) {
            EventType::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}
