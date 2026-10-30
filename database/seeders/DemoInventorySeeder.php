<?php

namespace Database\Seeders;

use App\Models\Categories;
use App\Models\Product;
use Database\Seeders\Support\InventoryDaySimulator;
use Illuminate\Database\Seeder;

/**
 * Local/staging demo data: a small drinks catalogue (only if there are no products yet) and
 * ~6 weeks of consistent trading history.
 *
 *   php artisan db:seed --class=DemoInventorySeeder
 */
class DemoInventorySeeder extends Seeder
{
    /** name => [selling price in GH₵, units per box] */
    private const CATALOGUE = [
        'Voltic Water' => [3, 12],
        'Coca-Cola' => [8, 24],
        'Fanta' => [8, 24],
        'Sprite' => [8, 24],
        'Malta Guinness' => [12, 24],
        'Club Beer' => [15, 24],
        'Star Beer' => [15, 24],
        'Guinness Stout' => [18, 24],
        'Smirnoff Ice' => [15, 24],
        'Heineken' => [20, 24],
    ];

    public function run(): void
    {
        abort_if(app()->environment('production'), 403, 'Demo data cannot be generated in production.');

        if (Product::count() === 0) {
            $this->createCatalogue();
        }

        $stats = app(InventoryDaySimulator::class)->simulate(days: 45);

        $this->command?->info(sprintf(
            'Simulated %d trading day(s) (%s to %s): %d stock rows, %d daily sales rows.',
            $stats['days'],
            $stats['from'] ?? '-',
            $stats['to'] ?? '-',
            $stats['stock_rows'],
            $stats['daily_sales'],
        ));
    }

    private function createCatalogue(): void
    {
        $category = Categories::firstOrCreate(['name' => 'drinks'], ['pricing_model' => 'per_unit']);

        foreach (self::CATALOGUE as $name => [$price, $perBox]) {
            Product::firstOrCreate(['name' => $name], [
                'category_id' => $category->id,
                'sku' => 'DEMO-'.strtoupper(substr(md5($name), 0, 6)),
                'barcode' => 'DEMO'.substr(md5('b'.$name), 0, 9),
                'selling_price' => $price,
                'units_per_box' => $perBox,
                'stock_limit' => $perBox * 2,
            ]);
        }
    }
}
