<?php

namespace Tests\Feature;

use App\Livewire\Pages\Inventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Credit and damaged units were removed from end-of-day stock taking. The database columns and the
 * credit_sales table are deliberately left in place (production data is not touched), so these
 * tests pin down that the app no longer reads or writes them.
 */
class InventoryNoCreditTest extends TestCase
{
    use RefreshDatabase;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 16:00:00'); // a Wednesday afternoon

        $category = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $this->productId = DB::table('products')->insertGetId([
            'name' => 'Club Beer', 'category_id' => $category, 'selling_price' => 10, 'units_per_box' => 12,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('stocks')->insert([
            'product_id' => $this->productId, 'total_units' => 100, 'cost_price' => 6, 'cost_margin' => 4, 'total_cost' => 600,
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);

        $roleId = DB::table('roles')->insertGetId(['name' => 'Stock Tester', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs(User::forceCreate([
            'name' => 'Kofi', 'username' => 'kofi', 'phone' => '0241112222', 'role_id' => $roleId,
            'password' => '482913', 'must_change_password' => false,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function takeInventory(string $closing = '40'): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('cashAmount', '500')
            ->set('momoAmount', '100')
            ->set('hubtelAmount', '0')
            ->set('snooker', '0')
            ->set('foodTotal', '0')
            ->set("productStocks.{$this->productId}.closing_units", $closing)
            ->call('submitInventory');
    }

    public function test_the_stock_form_has_no_credit_or_damaged_inputs(): void
    {
        Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('cashAmount', '500')
            ->set('momoAmount', '100')
            ->set('hubtelAmount', '0')
            ->set('snooker', '0')
            ->set('foodTotal', '0')
            ->call('nextStep') // step 2 is the stock count
            ->assertSee('Closing Units')
            ->assertSee('Expected')
            ->assertDontSee('Credit Units')
            ->assertDontSee('Damaged Units')
            ->assertDontSee('Credit Customer Information');
    }

    public function test_taking_inventory_sells_opening_minus_closing_and_writes_no_credit_or_damage(): void
    {
        $this->takeInventory('40')->assertHasNoErrors();

        $sale = DB::table('daily_sales')->first();
        $this->assertSame(100.0, (float) $sale->opening_stock);
        $this->assertSame(40.0, (float) $sale->closing_stock);
        $this->assertSame(600.0, (float) $sale->total_amount); // 60 sold x 10
        $this->assertSame(0.0, (float) $sale->credit_units);
        $this->assertSame(0.0, (float) $sale->credit_amount);
        $this->assertSame(0.0, (float) $sale->damaged_units);
        $this->assertSame(0.0, (float) $sale->loss_amount);

        $summary = DB::table('daily_sales_summaries')->first();
        $this->assertSame(600.0, (float) $summary->drinks_total);
        $this->assertSame(60.0, (float) $summary->items_sold);
        $this->assertSame(240.0, (float) $summary->total_profit); // 60 x margin 4
        $this->assertSame(0.0, (float) $summary->total_credit_amount);
        $this->assertSame(0.0, (float) $summary->total_credit_units);
        $this->assertSame(0.0, (float) $summary->total_damaged);
        $this->assertSame(0.0, (float) $summary->total_loss_amount);

        $this->assertSame(0, DB::table('credit_sales')->count());

        $stockMeta = json_decode(DB::table('stocks')->orderByDesc('id')->value('metadata'), true);
        $movementMeta = json_decode(DB::table('stock_movements')->orderByDesc('id')->value('metadata'), true);
        foreach ([$stockMeta, $movementMeta] as $meta) {
            $this->assertArrayNotHasKey('credit_units', $meta);
            $this->assertArrayNotHasKey('damaged_units', $meta);
        }
        $this->assertSame(60, (int) $movementMeta['units_sold']);
    }

    public function test_inventory_can_be_taken_and_edited_at_any_time_of_day(): void
    {
        foreach (['2026-10-28 02:15:00', '2026-10-28 09:30:00', '2026-10-28 14:59:00'] as $time) {
            Carbon::setTestNow($time);

            // Start each pass from the same opening position.
            foreach (['daily_sales', 'daily_sales_summaries', 'stock_movements', 'stocks'] as $table) {
                DB::table($table)->delete();
            }
            DB::table('stocks')->insert([
                'product_id' => $this->productId, 'total_units' => 100, 'cost_price' => 6, 'cost_margin' => 4, 'total_cost' => 600,
                'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
            ]);

            $this->takeInventory('40')->assertHasNoErrors();
            $this->assertSame(1, DB::table('daily_sales')->count(), "taking inventory at $time");

            Livewire::test(Inventory::class)
                ->call('openEditModal', DB::table('daily_sales_summaries')->value('id'))
                ->set("productStocks.{$this->productId}.closing_units", '30')
                ->call('updateInventory')
                ->assertHasNoErrors();

            $this->assertSame(30.0, (float) DB::table('daily_sales')->value('closing_stock'), "editing at $time");
        }
    }

    public function test_closing_stock_above_opening_is_still_refused(): void
    {
        $this->takeInventory('150');

        $this->assertSame(0, DB::table('daily_sales')->count());
    }

    public function test_editing_a_day_recalculates_without_credit_or_damage(): void
    {
        $this->takeInventory('40');
        $summaryId = DB::table('daily_sales_summaries')->value('id');

        Livewire::test(Inventory::class)
            ->call('openEditModal', $summaryId)
            ->assertDontSee('Credit Units')
            ->assertDontSee('Damaged Units')
            ->set("productStocks.{$this->productId}.closing_units", '30')
            ->call('updateInventory')
            ->assertHasNoErrors();

        $sale = DB::table('daily_sales')->first();
        $this->assertSame(1, DB::table('daily_sales')->count());
        $this->assertSame(30.0, (float) $sale->closing_stock);
        $this->assertSame(700.0, (float) $sale->total_amount);
        $this->assertSame(0.0, (float) $sale->credit_units);
        $this->assertSame(0.0, (float) $sale->damaged_units);

        $summary = DB::table('daily_sales_summaries')->first();
        $this->assertSame(700.0, (float) $summary->drinks_total);
        $this->assertSame(0, DB::table('credit_sales')->count());
    }

    public function test_the_day_details_show_no_credit_or_damage_columns(): void
    {
        $this->takeInventory('40');
        $summaryId = DB::table('daily_sales_summaries')->value('id');

        Livewire::test(Inventory::class)
            ->call('openDetailsModal', $summaryId)
            ->assertSee('Product Breakdown')
            ->assertSee('Units Sold')
            ->assertDontSee('Credited')
            ->assertDontSee('Damaged')
            ->assertDontSee('Adjustments');
    }

    public function test_analytics_stock_movements_and_the_export_still_work_on_the_new_numbers(): void
    {
        $this->takeInventory('40');
        $date = DB::table('daily_sales')->value('sales_date');

        // Analytics' month picker uses PostgreSQL's TO_CHAR; give the SQLite test database an equivalent.
        DB::connection()->getPdo()->sqliteCreateFunction('TO_CHAR', fn ($value) => substr((string) $value, 0, 7));

        $analytics = Livewire::test(\App\Livewire\Pages\Analytics::class)
            ->set('startDate', $date)
            ->set('endDate', $date)
            ->assertSee('Units Sold')
            ->assertDontSee('Damage rate')
            ->call('loadCharts'); // product, category, daily and payment-method breakdowns

        $this->assertSame(60, (int) $analytics->get('totalItemsSold'));
        $this->assertEqualsWithDelta(600, (float) $analytics->get('drinksTotal'), 0.01);
        $this->assertSame(['Cash', 'Mobile Money', 'Hubtel'], collect($analytics->get('paymentMethodDistribution'))->pluck('method')->all());
        $this->assertSame(60, (int) $analytics->get('topSellingProducts')[0]['units_sold']);
        $this->assertEqualsWithDelta(600, (float) $analytics->get('categoryPerformance')[0]['revenue'], 0.01);

        Livewire::test(\App\Livewire\Pages\StockMovements::class)
            ->call('openTrendModal', $this->productId)
            ->assertSee('Total Sold')
            ->assertDontSee('Damaged')
            ->assertDontSee('Credits:');

        $sheet = new \App\Exports\Sheets\DailySalesSheet($date, $date);
        $this->assertNotContains('Damaged Units', $sheet->headings());
        $this->assertNotContains('Credit Units', $sheet->headings());

        $row = $sheet->collection()->first();
        $this->assertCount(count($sheet->headings()), $sheet->map($row));
        $this->assertSame(60, (int) $row->units_sold);
    }

    public function test_the_unpaid_credits_card_is_gone_from_the_dashboard_component(): void
    {
        $this->assertFalse(method_exists(\App\Livewire\Pages\Dashboard::class, 'markCreditAsPaid'));

        Livewire::test(\App\Livewire\Pages\Dashboard::class)->assertOk()->assertDontSee('Unpaid Credits');
        $this->assertFileDoesNotExist(app_path('Models/CreditSales.php'));
    }

    public function test_the_database_schema_is_left_untouched(): void
    {
        // The credit/damaged columns and credit_sales stay as they are in production: only the
        // original create migrations may mention them (no drop or alter migration was added).
        $original = [
            '2025_07_15_181459_create_daily_sales_table.php',
            '2025_07_31_141913_create_daily_sales_summaries_table.php',
            '2026_02_13_004352_create_credit_sales_table.php',
        ];

        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (in_array(basename($file), $original, true)) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/credit_sales|credit_units|credit_amount|damaged_units|loss_amount|total_damaged|total_credit_/',
                file_get_contents($file),
                basename($file).' must not touch the credit/damaged columns'
            );
        }

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('credit_sales'));
        foreach (['credit_units', 'credit_amount', 'damaged_units', 'loss_amount'] as $column) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('daily_sales', $column), $column);
        }
        foreach (['total_credit_units', 'total_credit_amount', 'total_damaged', 'total_loss_amount'] as $column) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('daily_sales_summaries', $column), $column);
        }
    }
}
