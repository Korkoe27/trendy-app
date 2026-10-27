<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();

        $units = fake()->numberBetween(20, 100);
        $price = (float) $product->selling_price;
        $costPrice = round($price * fake()->randomFloat(2, 0.55, 0.80), 2);
        $at = now()->subDays(fake()->numberBetween(1, 30));

        return [
            'product_id' => $product->id,
            'total_units' => $units,
            'free_units' => 0,
            'supplier_id' => Supplier::inRandomOrder()->first()?->id,
            'total_cost' => round($costPrice * $units, 2),
            'cost_price' => $costPrice,
            'cost_margin' => round($price - $costPrice, 2),
            'notes' => null,
            'metadata' => json_encode(['demo' => true]),
            'restock_date' => $at,
            'occurred_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }

    /**
     * A purchase: adds $units on top of the product's current stock, the same way
     * Stocks::saveNewStock() builds its running total.
     */
    public function restock(int $units, Carbon $at, ?Supplier $supplier = null, ?float $costPrice = null): static
    {
        return $this->state(function (array $attributes) use ($units, $at, $supplier, $costPrice) {
            $product = Product::findOrFail($attributes['product_id']);
            $latest = Stock::where('product_id', $product->id)->latest('created_at')->latest('id')->first();

            $price = (float) $product->selling_price;
            $costPrice ??= (float) ($latest?->cost_price ?: round($price * 0.65, 2));
            $previous = (int) ($latest?->total_units ?? 0);

            return [
                'total_units' => $previous + $units,
                'free_units' => 0,
                'supplier_id' => $supplier?->id ?? $latest?->supplier_id,
                'total_cost' => round($costPrice * $units, 2),
                'cost_price' => round($costPrice, 2),
                'cost_margin' => round($price - $costPrice, 2),
                'notes' => $latest ? 'Restock (demo)' : 'Opening stock (demo)',
                'metadata' => json_encode(['demo' => true]),
                'restock_date' => $at,
                'occurred_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        });
    }

    /**
     * An end-of-day inventory row, shaped like Inventory::actuallySubmitInventory() writes it.
     * $enteredAt is when the count was typed in (the day after the business date).
     */
    public function closing(Stock $opening, float $sold, float $damaged, float $credit, Carbon $businessDate, Carbon $enteredAt): static
    {
        return $this->state(function () use ($opening, $sold, $damaged, $credit, $businessDate, $enteredAt) {
            $price = (float) $opening->product->selling_price;
            $closingUnits = (int) round($opening->total_units - $sold - $damaged - $credit);

            return [
                'product_id' => $opening->product_id,
                'total_units' => $closingUnits,
                'free_units' => 0,
                'supplier_id' => $opening->supplier_id,
                'total_cost' => $opening->total_cost,
                'cost_price' => $opening->cost_price,
                'cost_margin' => round($price - (float) $opening->cost_price, 2),
                'notes' => 'End of day inventory - '.$businessDate->toDateString(),
                'restock_date' => $opening->restock_date,
                'occurred_at' => $businessDate->copy()->startOfDay(),
                'metadata' => json_encode([
                    'source' => 'daily_inventory',
                    'previous_stock_date' => $opening->created_at?->toDateTimeString(),
                    'opening_stock' => $opening->total_units,
                    'units_sold' => $sold,
                    'damaged_units' => $damaged,
                    'credit_units' => $credit,
                    'created_by' => null,
                    'demo' => true,
                ]),
                'created_at' => $enteredAt,
                'updated_at' => $enteredAt,
            ];
        });
    }

    /**
     * Every stock row gets its ledger twin (delta = this row's total minus the previous row's),
     * matching what the live write paths and the backfill command produce.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Stock $stock) {
            $previous = Stock::where('product_id', $stock->product_id)
                ->where('id', '!=', $stock->id)
                ->where(function ($q) use ($stock) {
                    $q->where('created_at', '<', $stock->created_at)
                        ->orWhere(fn ($q) => $q->where('created_at', $stock->created_at)->where('id', '<', $stock->id));
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $metadata = json_decode($stock->metadata ?? '[]', true) ?: [];
            $isInventory = ($metadata['source'] ?? null) === 'daily_inventory';
            $delta = $stock->total_units - (int) ($previous?->total_units ?? 0);

            $movementMeta = ['demo' => true];

            if ($isInventory) {
                $movementMeta += [
                    'units_sold' => $metadata['units_sold'] ?? null,
                    'damaged_units' => $metadata['damaged_units'] ?? null,
                    'credit_units' => $metadata['credit_units'] ?? null,
                    'opening_stock' => $metadata['opening_stock'] ?? null,
                    'closing_stock' => $stock->total_units,
                ];
            }

            DB::table('stock_movements')->insert([
                'product_id' => $stock->product_id,
                'type' => $isInventory ? 'inventory' : 'purchase',
                'quantity_delta' => $delta,
                'reference_type' => 'stock',
                'reference_id' => $stock->id,
                'occurred_at' => $stock->occurred_at ?? $stock->created_at,
                'metadata' => json_encode($movementMeta),
                'created_at' => $stock->created_at,
                'updated_at' => $stock->created_at,
            ]);
        });
    }
}
