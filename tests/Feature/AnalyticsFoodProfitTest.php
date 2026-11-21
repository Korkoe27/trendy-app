<?php

namespace Tests\Feature;

use App\Livewire\Pages\Analytics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Food profit = food sold - foodstuff bought in the same period. */
class AnalyticsFoodProfitTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        // Analytics' month picker uses PostgreSQL's TO_CHAR; give the SQLite test database an equivalent.
        DB::connection()->getPdo()->sqliteCreateFunction('TO_CHAR', fn ($value) => substr((string) $value, 0, 7));

        $roleId = DB::table('roles')->insertGetId(['name' => 'Analytics Tester', 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Drinks', 'created_at' => now(), 'updated_at' => now()]);
        $this->productId = DB::table('products')->insertGetId(['name' => 'Water', 'category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()]);

        $this->staff = User::forceCreate([
            'name' => 'Ama', 'username' => 'ama', 'phone' => '0241112222', 'role_id' => $roleId,
            'password' => '482913', 'must_change_password' => false,
        ]);
        $this->actingAs($this->staff);
    }

    private function analytics(string $start = '2026-03-01', string $end = '2026-03-31')
    {
        return Livewire::test(Analytics::class)
            ->set('timeframe', 'custom')
            ->set('startDate', $start)
            ->set('endDate', $end);
    }

    private function foodSold(string $date, float $amount): void
    {
        DB::table('daily_sales_summaries')->insert([
            'sales_date' => $date,
            'drinks_total' => 0,
            'food_total' => $amount,
            'product_id' => $this->productId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bought(string $date, float $amount, string $category = 'foodstuff'): void
    {
        DB::table('expenses')->insert([
            'reference' => 'SHP-'.uniqid(),
            'amount' => $amount,
            'description' => 'Foodstuff shopping',
            'incurred_at' => $date.' 12:00:00',
            'payment_method' => 'cash',
            'paid_by' => 'Ama',
            'category' => $category,
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_food_profit_is_food_sold_minus_foodstuff_bought(): void
    {
        $this->foodSold('2026-03-03', 500);
        $this->foodSold('2026-03-10', 300);
        $this->bought('2026-03-05', 250);
        $this->bought('2026-03-12', 150);
        $this->bought('2026-03-06', 900, 'inventory'); // a drinks restock, not foodstuff

        $analytics = $this->analytics();

        $this->assertEqualsWithDelta(800, (float) $analytics->get('foodRevenue'), 0.01);
        $this->assertEqualsWithDelta(400, (float) $analytics->get('foodCost'), 0.01);
        $this->assertEqualsWithDelta(400, (float) $analytics->get('foodProfit'), 0.01);
        $this->assertEqualsWithDelta(50, (float) $analytics->get('foodMargin'), 0.1);
        $this->assertSame(2, (int) $analytics->get('foodPurchasesCount'));
        $analytics->assertSee('Food Profit')->assertSee('Profit: GH₵ 400.00');
    }

    public function test_food_shows_a_loss_when_foodstuff_costs_more_than_the_food_sold(): void
    {
        $this->foodSold('2026-03-03', 200);
        $this->bought('2026-03-05', 500);

        $analytics = $this->analytics();

        $this->assertEqualsWithDelta(-300, (float) $analytics->get('foodProfit'), 0.01);
        $analytics->assertSee('Loss: GH₵ 300.00')->assertSee('Food Margin');
    }

    public function test_foodstuff_bought_outside_the_period_is_ignored(): void
    {
        $this->foodSold('2026-03-03', 500);
        $this->bought('2026-03-05', 100);
        $this->bought('2026-02-20', 999); // previous month

        $analytics = $this->analytics();

        $this->assertEqualsWithDelta(500, (float) $analytics->get('foodRevenue'), 0.01);
        $this->assertEqualsWithDelta(100, (float) $analytics->get('foodCost'), 0.01);
        $this->assertSame(1, (int) $analytics->get('foodPurchasesCount'));
    }
}
