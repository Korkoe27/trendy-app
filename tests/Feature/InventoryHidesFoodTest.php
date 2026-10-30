<?php

namespace Tests\Feature;

use App\Livewire\Pages\Inventory;
use App\Models\Categories;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Food is sold through orders and counted as one "Food total"; it is never part of the unit count. */
class InventoryHidesFoodTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private int $rice;

    private int $plateFood;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 16:00:00'); // a Wednesday afternoon

        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'pricing_model' => 'per_unit', 'created_at' => now(), 'updated_at' => now()]);
        $food = DB::table('categories')->insertGetId(['name' => 'Food', 'created_at' => now(), 'updated_at' => now()]);
        $kitchen = DB::table('categories')->insertGetId(['name' => 'kitchen', 'pricing_model' => 'per_plate', 'created_at' => now(), 'updated_at' => now()]);

        $this->beer = $this->product($drinks, 'Club Beer', 10);
        $this->rice = $this->product($food, 'Jollof Rice', 75);
        $this->plateFood = $this->product($kitchen, 'Waakye', 40);

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

    private function product(int $categoryId, string $name, float $price): int
    {
        $id = DB::table('products')->insertGetId([
            'name' => $name, 'category_id' => $categoryId, 'selling_price' => $price, 'units_per_box' => 12,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('stocks')->insert([
            'product_id' => $id, 'total_units' => 100, 'cost_price' => 6, 'cost_margin' => 4, 'total_cost' => 600,
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);

        return $id;
    }

    private function toStockStep(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(Inventory::class)
            ->call('openTakeInventoryModal')
            ->set('cashAmount', '500')
            ->set('momoAmount', '100')
            ->set('hubtelAmount', '0')
            ->set('snooker', '0')
            ->set('foodTotal', '0')
            ->call('nextStep');
    }

    public function test_food_categories_are_found_by_name_or_per_plate_pricing(): void
    {
        $this->assertEqualsCanonicalizing(
            [(int) Categories::where('name', 'Food')->value('id'), (int) Categories::where('name', 'kitchen')->value('id')],
            Categories::foodIds(),
        );
    }

    public function test_the_stock_count_lists_drinks_but_no_food(): void
    {
        $this->toStockStep()
            ->assertSee('Club Beer')
            ->assertDontSee('Jollof Rice')
            ->assertDontSee('Waakye');
    }

    public function test_food_never_becomes_a_sales_row_even_if_a_request_adds_it(): void
    {
        $this->toStockStep()
            ->set("productStocks.{$this->beer}.closing_units", '40')
            ->set("productStocks.{$this->rice}", ['closing_boxes' => '', 'closing_units' => '10', 'product' => null, 'stock' => null])
            ->call('submitInventory')
            ->assertHasNoErrors();

        $this->assertSame([$this->beer], DB::table('daily_sales')->pluck('product_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(600.0, (float) DB::table('daily_sales_summaries')->value('drinks_total'));
    }

    public function test_editing_a_past_day_that_already_has_food_keeps_it(): void
    {
        // A day recorded back when food was still counted unit by unit.
        DB::table('daily_sales_summaries')->insert([
            'sales_date' => '2026-10-20', 'drinks_total' => 0, 'food_total' => 0, 'on_the_house' => 0, 'total_cash' => 0,
            'total_momo' => 0, 'total_hubtel' => 0, 'product_id' => $this->beer, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $summaryId = DB::table('daily_sales_summaries')->value('id');

        foreach ([$this->beer, $this->rice] as $productId) {
            DB::table('daily_sales')->insert([
                'product_id' => $productId, 'stock_id' => DB::table('stocks')->where('product_id', $productId)->value('id'),
                'sales_date' => '2026-10-20', 'opening_stock' => 100, 'closing_stock' => 90,
                'total_amount' => 100, 'unit_profit' => 40, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Livewire::test(Inventory::class)
            ->call('openEditModal', $summaryId)
            ->call('nextStep') // step 2 is the stock count
            ->assertSee('Jollof Rice')
            ->assertSee('Club Beer');
    }
}
