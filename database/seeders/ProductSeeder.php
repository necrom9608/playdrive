<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'game-inn')->firstOrFail();

        $categories = [
            'toegang' => ProductCategory::where('tenant_id', $tenant->id)->where('slug', 'toegang')->firstOrFail(),
            'cadeaubonnen' => ProductCategory::where('tenant_id', $tenant->id)->where('slug', 'cadeaubonnen')->firstOrFail(),
            'snacks' => ProductCategory::where('tenant_id', $tenant->id)->where('slug', 'snacks')->firstOrFail(),
            'drank' => ProductCategory::where('tenant_id', $tenant->id)->where('slug', 'drank')->firstOrFail(),
        ];

        $items = [
            ['category' => 'toegang', 'name' => 'Abonnement kind / student', 'price' => 119.00, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Abonnement volwassene', 'price' => 139.00, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Familieabonnement', 'price' => 249.00, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Inkom kind / student 1u', 'price' => 12.90, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Inkom kind / student 2u', 'price' => 19.90, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Inkom kind / student dagticket', 'price' => 24.90, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Pannenkoeken', 'price' => 5.00, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Goodiebag', 'price' => 5.00, 'vat' => 21],
            ['category' => 'toegang', 'name' => 'Inkom volwassene 1u', 'price' => 14.90, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Inkom volwassene 2u', 'price' => 24.90, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Inkom volwassene dagticket', 'price' => 29.90, 'vat' => 6],
            ['category' => 'toegang', 'name' => 'Pizzabuffet', 'price' => 15.00, 'vat' => 12],
            ['category' => 'toegang', 'name' => 'Toeslag extra tijd', 'price' => 2.00, 'vat' => 6],

            ['category' => 'cadeaubonnen', 'name' => 'Cadeaubon kind / student 2u', 'price' => 19.90, 'vat' => 6],
            ['category' => 'cadeaubonnen', 'name' => 'Cadeaubon kind / student dagticket', 'price' => 24.90, 'vat' => 6],
            ['category' => 'cadeaubonnen', 'name' => 'Cadeaubon volwassene 2u', 'price' => 24.90, 'vat' => 6],
            ['category' => 'cadeaubonnen', 'name' => 'Cadeaubon volwassene dagticket', 'price' => 29.90, 'vat' => 6],

            ['category' => 'snacks', 'name' => 'Chips paprika', 'price' => 2.80, 'vat' => 6],
            ['category' => 'snacks', 'name' => 'Chips zout', 'price' => 2.80, 'vat' => 6],
            ['category' => 'snacks', 'name' => 'Chips grills', 'price' => 2.80, 'vat' => 6],
            ['category' => 'snacks', 'name' => 'Popcorn', 'price' => 6.80, 'vat' => 6],
            ['category' => 'snacks', 'name' => 'Kippensoep', 'price' => 2.80, 'vat' => 6],
            ['category' => 'snacks', 'name' => 'Tomatensoep', 'price' => 3.50, 'vat' => 6],

            ['category' => 'drank', 'name' => 'Coca-Cola', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Coca-Cola Zero', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Fanta', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Sprite', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Eaumega citroen', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Fusetea peach', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Spa blauw', 'price' => 2.80, 'vat' => 6],
            ['category' => 'drank', 'name' => 'Spa rood', 'price' => 2.80, 'vat' => 6],
            ['category' => 'drank', 'name' => 'Appelsap', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Looza Ace', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Chocomelk', 'price' => 3.50, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Koffie', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Koffie décafe', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Tea', 'price' => 2.80, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Jupiler', 'price' => 3.50, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Jupiler 0%', 'price' => 3.50, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Omer', 'price' => 4.50, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Duvel', 'price' => 4.50, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Witte wijn', 'price' => 4.50, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Rode wijn', 'price' => 4.50, 'vat' => 21],
            ['category' => 'drank', 'name' => 'Cava', 'price' => 4.50, 'vat' => 21],
        ];

        foreach ($items as $index => $item) {
            $priceInclVat = (float) $item['price'];
            $vatRate = (float) $item['vat'];
            $priceExclVat = round($priceInclVat / (1 + ($vatRate / 100)), 2);
            $slug = Str::slug($item['name']);

            Product::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'slug' => $slug,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'product_category_id' => $categories[$item['category']]->id,
                    'name' => $item['name'],
                    'slug' => $slug,
                    'description' => null,
                    'price_excl_vat' => $priceExclVat,
                    'vat_rate' => $vatRate,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
