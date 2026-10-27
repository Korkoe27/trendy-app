<?php

namespace Database\Seeders\Support;

use App\Models\CreditSales;
use App\Models\DailySales;
use App\Models\DailySalesSummary;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use Carbon\Carbon;
use Database\Factories\CreditSalesFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates believable trading history (stock, daily sales, daily summaries, credit sales and
 * ledger rows) that reconciles end to end. Local/staging only. Requires dev dependencies (faker),
 * because it builds rows through the model factories.
 */
class InventoryDaySimulator
{
    /** ISO weekday numbers the pub is closed (1 = Monday), same as Inventory::CLOSED_DAYS. */
    private const CLOSED_DAYS = [1];

    /** Busy-ness by ISO weekday. */
    private const DAY_FACTOR = [2 => 0.7, 3 => 0.8, 4 => 0.9, 5 => 1.6, 6 => 2.0, 7 => 1.2];

    /**
     * @return array{days: int, from: ?string, to: ?string, stock_rows: int, daily_sales: int, credit_rows: int, credit_amount: float}
     */
    public function simulate(int $days = 45, bool $fresh = false, ?int $seed = null): array
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo data cannot be generated in production.');
        }

        if ($seed !== null) {
            mt_srand($seed);
        }

        if ($fresh) {
            $this->cleanup();
        }

        $products = Product::where('is_active', true)->get();

        if ($products->isEmpty()) {
            throw new \RuntimeException('No active products found. Create products first (or run DemoInventorySeeder).');
        }

        $suppliers = (Supplier::count() ? Supplier::all() : Supplier::factory()->count(3)->create())->values();

        $lastDay = now()->subDay()->startOfDay();
        $firstDay = $this->firstDay($lastDay->copy()->subDays(max(1, $days) - 1));

        $stats = ['days' => 0, 'from' => null, 'to' => null, 'stock_rows' => 0, 'daily_sales' => 0, 'credit_rows' => 0, 'credit_amount' => 0.0];

        if ($firstDay->gt($lastDay)) {
            return $stats;
        }

        $base = $products->mapWithKeys(fn ($p) => [$p->id => $this->baseDailyUnits($p)]);

        // The latest stock row per product, kept in memory so each new row chains off the previous one.
        $current = $this->latestStocks($products->pluck('id'));

        // Products with no stock at all get an opening purchase the day before trading starts.
        foreach ($products as $i => $p) {
            if (! isset($current[$p->id])) {
                $at = $firstDay->copy()->subDay()->setTime(10, 0, $i % 60);
                $current[$p->id] = $this->restock($p, $this->toBoxes($p, $base[$p->id] * 9), $at, $suppliers, $stats);
            }
        }

        for ($day = $firstDay->copy(); $day->lte($lastDay); $day->addDay()) {
            if (in_array($day->isoWeekday(), self::CLOSED_DAYS, true)) {
                continue;
            }

            DB::transaction(function () use ($day, $products, $suppliers, $base, &$current, &$stats) {
                $this->tradeOneDay($day->copy(), $products, $suppliers, $base, $current, $stats);
            });

            $stats['days']++;
            $stats['from'] ??= $day->toDateString();
            $stats['to'] = $day->toDateString();
        }

        return $stats;
    }

    public function cleanup(): void
    {
        DB::transaction(function () {
            DB::table('credit_sales')->where('metadata->demo', true)->delete();
            DB::table('daily_sales')->where('metadata->demo', true)->delete();
            DB::table('daily_sales_summaries')->where('metadata->demo', true)->delete();
            DB::table('stock_movements')->where('metadata->demo', true)->delete();
            DB::table('stocks')->where('metadata->demo', true)->delete();
        });
    }

    private function tradeOneDay(Carbon $day, Collection $products, Collection $suppliers, Collection $base, array &$current, array &$stats): void
    {
        $factor = self::DAY_FACTOR[$day->isoWeekday()];

        // Deliveries are entered in the evening, after the previous day's inventory was typed in.
        foreach ($products->values() as $i => $p) {
            $units = $current[$p->id]->total_units;
            $daily = $base[$p->id];
            $weeklyTopUp = $day->isoWeekday() === 2 && $units < $daily * 8;

            if ($units < $daily * 2.8 || $weeklyTopUp) {
                $needed = ($daily * 9) - $units;
                $at = $day->copy()->setTime(17, 30, $i % 60);
                $current[$p->id] = $this->restock($p, $this->toBoxes($p, $needed), $at, $suppliers, $stats);
            }
        }

        // Some days a few products go out on credit.
        $creditProducts = [];
        if ($this->chance(25)) {
            $ids = $products->pluck('id')->all();
            shuffle($ids);
            $creditProducts = array_slice($ids, 0, mt_rand(1, min(3, count($ids))));
        }

        $enteredDay = $day->copy()->addDay();
        $enteredAt = $enteredDay->copy()->setTime(15, mt_rand(0, 40), 0);
        $dailySales = collect();

        foreach ($products->values() as $i => $p) {
            $opening = $current[$p->id];

            if ($opening->total_units <= 0) {
                continue;
            }

            $available = (float) $opening->total_units;
            $damaged = $this->chance(3) ? mt_rand(1, 3) : 0;
            $credit = in_array($p->id, $creditProducts, true) ? mt_rand(1, 6) : 0;
            $sold = (int) round($base[$p->id] * $factor * (0.8 + mt_rand(0, 40) / 100));

            $damaged = min($damaged, $available);
            $credit = min($credit, $available - $damaged);
            $sold = max(0, min($sold, $available - $damaged - $credit));

            $rowAt = $enteredAt->copy()->addSeconds($i * 2);

            $closing = Stock::factory()
                ->forProduct($p)
                ->closing($opening, $sold, $damaged, $credit, $day, $rowAt)
                ->create();

            $sale = DailySales::factory()
                ->forClose($opening, $closing, $sold, $damaged, $credit, $day, $rowAt)
                ->create();

            if ($credit > 0) {
                $customer = CreditSalesFactory::CUSTOMERS[array_rand(CreditSalesFactory::CUSTOMERS)];
                CreditSales::factory()->forSale($sale, $customer)->create();
                $stats['credit_rows']++;
                $stats['credit_amount'] += (float) $sale->credit_amount;
            }

            $current[$p->id] = $closing->setRelation('product', $p);
            $dailySales->push($sale);
            $stats['stock_rows']++;
            $stats['daily_sales']++;
        }

        if ($dailySales->isEmpty()) {
            return;
        }

        $roll = mt_rand(1, 100);

        DailySalesSummary::factory()
            ->forDay($day, $enteredAt->copy()->addMinutes(5), $dailySales, [
                'food' => 5 * mt_rand(40, 180),
                'snooker' => $this->chance(40) ? 5 * mt_rand(10, 60) : 0,
                'on_the_house' => $this->chance(30) ? 5 * mt_rand(1, 8) : 0,
                'shortfall' => ($roll > 85 && $roll <= 95) ? 5 * mt_rand(1, 5) : 0,
                'overage' => $roll > 95 ? 5 * mt_rand(1, 3) : 0,
                'momo_share' => 0.30 + mt_rand(0, 10) / 100,
                'hubtel_share' => 0.05 + mt_rand(0, 7) / 100,
            ])
            ->create();
    }

    private function restock(Product $p, int $units, Carbon $at, Collection $suppliers, array &$stats): Stock
    {
        $latest = Stock::where('product_id', $p->id)->latest('created_at')->latest('id')->first();
        $lastCost = (float) ($latest?->cost_price ?: round((float) $p->selling_price * 0.65, 2));
        $costPrice = round($lastCost * (0.97 + mt_rand(0, 8) / 100), 2);

        $stats['stock_rows']++;

        return Stock::factory()
            ->forProduct($p)
            ->restock(max($units, 1), $at, $suppliers[$p->id % $suppliers->count()], $costPrice)
            ->create()
            ->setRelation('product', $p);
    }

    /** @return array<int, Stock> */
    private function latestStocks(Collection $productIds): array
    {
        $latest = [];

        foreach (Stock::with('product')->whereIn('product_id', $productIds)->orderBy('created_at')->orderBy('id')->get() as $stock) {
            $latest[$stock->product_id] = $stock;
        }

        return $latest;
    }

    /** First trading day we may generate, never before data that already exists. */
    private function firstDay(Carbon $requested): Carbon
    {
        $first = $requested->copy()->startOfDay();

        $latestStock = Stock::selectRaw('MAX(COALESCE(occurred_at, created_at)) as latest')->value('latest');
        $latestSale = DailySales::max('sales_date');

        foreach ([$latestStock, $latestSale] as $existing) {
            if ($existing) {
                $first = $first->max(Carbon::parse($existing)->startOfDay()->addDay());
            }
        }

        return $first;
    }

    /** Typical units sold on an average day: cheaper drinks move faster. Stable per product. */
    private function baseDailyUnits(Product $p): int
    {
        $price = max((float) $p->selling_price, 3.0);
        $popularity = 0.6 + (($p->id * 37) % 80) / 100;

        return (int) max(4, min(60, round((90 / $price) * $popularity)));
    }

    private function toBoxes(Product $p, float $units): int
    {
        $box = (int) $p->units_per_box > 0 ? (int) $p->units_per_box : 12;

        return (int) (ceil(max($units, 1) / $box) * $box);
    }

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }
}
