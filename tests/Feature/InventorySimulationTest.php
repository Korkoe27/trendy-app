<?php

namespace Tests\Feature;

use App\Services\DebtLedger;
use Carbon\Carbon;
use Database\Seeders\Support\InventoryDaySimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventorySimulationTest extends TestCase
{
    use RefreshDatabase;

    private array $stats;

    protected function setUp(): void
    {
        parent::setUp();

        $categoryId = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);

        foreach ([['Water', 3, 12], ['Coke', 8, 24], ['Club Beer', 15, 24], ['Guinness', 18, 24]] as [$name, $price, $box]) {
            DB::table('products')->insert([
                'name' => $name, 'category_id' => $categoryId, 'selling_price' => $price,
                'units_per_box' => $box, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->stats = (new InventoryDaySimulator)->simulate(days: 28, seed: 99);
    }

    public function test_it_generates_trading_days_but_never_on_mondays(): void
    {
        $this->assertGreaterThan(15, $this->stats['days']);

        $dates = DB::table('daily_sales')->distinct()->pluck('sales_date');
        $this->assertCount($this->stats['days'], $dates);

        foreach ($dates as $date) {
            $this->assertNotSame(1, Carbon::parse($date)->isoWeekday(), "Sales recorded on a Monday: $date");
        }
    }

    public function test_every_daily_sales_row_reconciles_with_its_stock_rows(): void
    {
        foreach (DB::table('daily_sales')->get() as $sale) {
            $closing = DB::table('stocks')->find($sale->stock_id);
            $sold = $sale->opening_stock - $sale->closing_stock;

            $this->assertEquals($closing->total_units, $sale->closing_stock);
            $this->assertGreaterThanOrEqual(0, $sold, 'Negative units sold');
            $this->assertGreaterThanOrEqual(0, $sale->closing_stock, 'Negative stock');
            $this->assertEqualsWithDelta($sold * (float) DB::table('products')->find($sale->product_id)->selling_price, $sale->total_amount, 0.01);

            // The opening figure must be the stock row that came right before this closing row.
            $previous = DB::table('stocks')
                ->where('product_id', $sale->product_id)
                ->where(fn ($q) => $q->where('created_at', '<', $closing->created_at)
                    ->orWhere(fn ($q) => $q->where('created_at', $closing->created_at)->where('id', '<', $closing->id)))
                ->orderByDesc('created_at')->orderByDesc('id')->first();

            $this->assertEquals($previous->total_units, $sale->opening_stock, 'Opening does not match the previous stock row');
        }
    }

    public function test_ledger_sums_to_current_stock_for_every_product(): void
    {
        foreach (DB::table('products')->pluck('id') as $productId) {
            $latest = DB::table('stocks')->where('product_id', $productId)->orderByDesc('created_at')->orderByDesc('id')->first();
            $ledger = DB::table('stock_movements')->where('product_id', $productId)->sum('quantity_delta');

            $this->assertEquals($latest->total_units, $ledger, "Ledger does not match stock for product $productId");
        }

        $this->assertSame(
            DB::table('stocks')->count(),
            DB::table('stock_movements')->where('reference_type', 'stock')->count(),
            'Every stock row should have exactly one ledger row'
        );
    }

    public function test_summaries_match_the_daily_rows_and_money_adds_up(): void
    {
        foreach (DB::table('daily_sales_summaries')->get() as $summary) {
            $sales = DB::table('daily_sales')->where('sales_date', $summary->sales_date)->get();

            $this->assertEqualsWithDelta($sales->sum('total_amount'), $summary->drinks_total, 0.01);
            $this->assertEqualsWithDelta(
                $summary->total_cash + $summary->total_momo + $summary->total_hubtel,
                $summary->total_money,
                0.01
            );

            // Expected minus collected is a small unexplained gap (-15..+25), zero on most days.
            $expected = $summary->drinks_total + $summary->food_total - $summary->on_the_house;
            $gap = $expected - $summary->total_money;
            $this->assertGreaterThanOrEqual(-15.01, $gap);
            $this->assertLessThanOrEqual(25.01, $gap);
        }
    }

    public function test_the_simulation_no_longer_involves_credit_or_damaged_units(): void
    {
        $this->assertArrayNotHasKey('credit_rows', $this->stats);
        $this->assertSame(0.0, (float) DB::table('daily_sales')->sum('credit_units'));
        $this->assertSame(0.0, (float) DB::table('daily_sales')->sum('damaged_units'));
        $this->assertSame(0, DB::table('credit_sales')->count());
    }

    public function test_shortfall_days_leave_debt_for_the_debt_payments_page(): void
    {
        $months = DB::table('daily_sales')->distinct()->pluck('sales_date')
            ->map(fn ($date) => Carbon::parse($date)->startOfMonth()->toDateString())
            ->unique();

        $debt = $months->sum(fn ($month) => (new DebtLedger)->monthlyDebt(Carbon::parse($month)));

        $this->assertGreaterThan(0, $debt);
    }

    public function test_running_again_continues_instead_of_duplicating_days(): void
    {
        $before = DB::table('daily_sales')->count();

        $again = (new InventoryDaySimulator)->simulate(days: 28, seed: 7);

        $this->assertSame(0, $again['days']);
        $this->assertSame($before, DB::table('daily_sales')->count());
    }

    public function test_fresh_removes_only_demo_rows_and_regenerates(): void
    {
        DB::table('daily_sales')->delete(); // keep FK-safe ordering for the manual real-row insert below
        DB::table('stock_movements')->delete();
        DB::table('stocks')->delete();

        $productId = DB::table('products')->value('id');
        $realStockId = DB::table('stocks')->insertGetId([
            'product_id' => $productId, 'total_units' => 5, 'created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60),
        ]);

        (new InventoryDaySimulator)->simulate(days: 10, seed: 1);
        $this->assertGreaterThan(1, DB::table('stocks')->count());

        (new InventoryDaySimulator)->cleanup();

        $this->assertSame(0, DB::table('daily_sales')->count());
        $this->assertSame(0, DB::table('daily_sales_summaries')->count());
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame([$realStockId], DB::table('stocks')->pluck('id')->all());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        (new InventoryDaySimulator)->simulate(days: 3);
    }
}
