<?php

namespace Tests\Feature;

use App\Livewire\Pages\Analytics;
use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\Products;
use App\Livewire\Pages\Stocks;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Only drinks have a stock process. Games (snooker) run on coins that come back every day, and
 * food, snacks and services are never stocked, so none of them is restocked, shown as "No Stock"
 * or counted in a low-stock alert.
 */
class OnlyDrinksStockedTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private int $snooker;

    private int $rice;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Analytics' month picker uses PostgreSQL's TO_CHAR; give the SQLite test database an equivalent.
        DB::connection()->getPdo()->sqliteCreateFunction('TO_CHAR', fn ($value) => substr((string) $value, 0, 7));

        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'pricing_model' => 'per_unit', 'created_at' => now(), 'updated_at' => now()]);
        $games = DB::table('categories')->insertGetId(['name' => 'games', 'pricing_model' => 'per_slot', 'created_at' => now(), 'updated_at' => now()]);
        $food = DB::table('categories')->insertGetId(['name' => 'food', 'pricing_model' => 'per_plate', 'created_at' => now(), 'updated_at' => now()]);

        $this->beer = $this->product($drinks, 'Club Beer', 10, stockLimit: 20);
        $this->snooker = $this->product($games, 'Snooker Table', 10, stockLimit: 5, coins: 200);
        $this->rice = $this->product($food, 'Jollof Rice', 75, stockLimit: 5);

        $roleId = DB::table('roles')->insertGetId(['name' => 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        foreach (['products', 'stocks', 'analytics', 'inventory'] as $module) {
            $moduleId = DB::table('modules')->where('name', $module)->value('id')
                ?? DB::table('modules')->insertGetId(['name' => $module, 'created_at' => now(), 'updated_at' => now()]);

            DB::table('permissions')->insert([
                'role_id' => $roleId, 'module_id' => $moduleId, 'can_view' => true, 'can_create' => true,
                'can_modify' => true, 'can_delete' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->admin = User::forceCreate([
            'name' => 'Admin', 'username' => 'admin', 'phone' => '0241112222', 'role_id' => $roleId,
            'password' => '482913', 'must_change_password' => false,
        ]);
        $this->actingAs($this->admin);
    }

    private function product(int $categoryId, string $name, float $price, int $stockLimit, ?int $coins = null): int
    {
        return DB::table('products')->insertGetId([
            'name' => $name, 'category_id' => $categoryId, 'selling_price' => $price, 'units_per_box' => 12,
            'stock_limit' => $stockLimit, 'daily_coins' => $coins, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** The Stocks page uses a PostgreSQL-only query to render, so these tests drive the component directly. */
    private function stocksPage(): Stocks
    {
        $page = new Stocks;
        $page->mount();

        return $page;
    }

    public function test_the_restock_picker_lists_only_drinks(): void
    {
        $this->assertSame(['Club Beer'], $this->stocksPage()->products->pluck('name')->all());
    }

    public function test_restocking_a_non_drink_is_refused_even_if_a_request_sends_it(): void
    {
        DB::table('suppliers')->insert(['name' => 'Acme', 'created_at' => now(), 'updated_at' => now()]);
        $supplierId = DB::table('suppliers')->value('id');

        $page = $this->stocksPage();
        $page->newStockItems = [[
            'product_id' => $this->snooker, 'input_boxes' => '', 'input_units' => '10', 'calculated_total_units' => 10,
            'free_units' => 0, 'total_cost' => '100', 'supplier_id' => $supplierId, 'new_supplier_name' => '',
            'calculated_cost_price' => 10, 'calculated_profit_margin' => 0,
        ]];
        $page->saveNewStock();

        $this->assertStringContainsString('Only drinks are stocked', $page->getErrorBag()->first('newStockItems'));
        $this->assertSame(0, DB::table('stocks')->where('product_id', $this->snooker)->count());
    }

    public function test_the_products_page_shows_not_stocked_and_coins_instead_of_no_stock(): void
    {
        $page = Livewire::test(Products::class);

        $page->assertSee('Coins reset daily')
            ->assertSee('200 coins/day')
            ->assertSee('Not stocked');

        // Drinks keep their stock status.
        $page->assertSee('No Stock');

        $this->assertSame('Not stocked', $page->instance()->getStockStatus($this->rice, 5)['text']);
        $this->assertSame('Coins reset daily', $page->instance()->getStockStatus($this->snooker, 5)['text']);
        $this->assertSame('No Stock', $page->instance()->getStockStatus($this->beer, 20)['text']);
    }

    public function test_a_game_needs_coins_per_day_and_a_drink_keeps_its_stock_limit(): void
    {
        $games = (int) DB::table('categories')->where('name', 'games')->value('id');
        $drinks = (int) DB::table('categories')->where('name', 'drinks')->value('id');

        $page = Livewire::test(Products::class)
            ->call('showCreateProductModal')
            ->set('newProducts.0.name', 'Pool Table')
            ->set('newProducts.0.category_id', (string) $games)
            ->set('newProducts.0.selling_price', '15')
            ->call('saveNewProducts')
            ->assertHasErrors('newProducts.0.daily_coins');

        $this->assertSame(0, DB::table('products')->where('name', 'pool table')->count());

        $page->set('newProducts.0.daily_coins', '120')->set('newProducts.0.stock_limit', '50')->call('saveNewProducts');

        $pool = DB::table('products')->where('name', 'pool table')->first();
        $this->assertSame(120, (int) $pool->daily_coins);
        $this->assertNull($pool->stock_limit, 'a game has no stock limit');

        Livewire::test(Products::class)
            ->call('showCreateProductModal')
            ->set('newProducts.0.name', 'Fanta')
            ->set('newProducts.0.category_id', (string) $drinks)
            ->set('newProducts.0.selling_price', '5')
            ->set('newProducts.0.stock_limit', '30')
            ->set('newProducts.0.daily_coins', '99')
            ->call('saveNewProducts');

        $fanta = DB::table('products')->where('name', 'fanta')->first();
        $this->assertSame(30, (int) $fanta->stock_limit);
        $this->assertNull($fanta->daily_coins, 'coins belong to games only');
    }

    public function test_editing_a_game_requires_its_coins_and_clears_the_stock_limit(): void
    {
        Livewire::test(Products::class)
            ->call('editProduct', $this->snooker)
            ->set('daily_coins', '')
            ->call('updateProduct')
            ->assertHasErrors('daily_coins')
            ->set('daily_coins', '150')
            ->call('updateProduct');

        $row = DB::table('products')->where('id', $this->snooker)->first();
        $this->assertSame(150, (int) $row->daily_coins);
        $this->assertNull($row->stock_limit);
    }

    public function test_the_dashboard_low_stock_list_only_contains_drinks(): void
    {
        $names = Livewire::test(Dashboard::class)->get('lowStockProducts')->pluck('name')->values()->all();

        $this->assertSame(['Club Beer'], $names, 'snooker and food have no stock, so they never alert');
    }

    public function test_analytics_stock_counts_ignore_non_drinks(): void
    {
        $analytics = Livewire::test(Analytics::class)
            ->set('timeframe', 'custom')
            ->set('startDate', '2026-03-01')
            ->set('endDate', '2026-03-31');

        $this->assertSame(1, (int) $analytics->get('outOfStockProducts'), 'only the beer');
        $this->assertSame(0, (int) $analytics->get('lowStockProducts'));

        // Stock the beer and give the snooker table a stock row too (legacy data): inventory value counts only drinks.
        DB::table('stocks')->insert([
            ['product_id' => $this->beer, 'total_units' => 10, 'cost_price' => 6, 'cost_margin' => 4, 'total_cost' => 60, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $this->snooker, 'total_units' => 100, 'cost_price' => 50, 'cost_margin' => 0, 'total_cost' => 5000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $analytics = Livewire::test(Analytics::class)
            ->set('timeframe', 'custom')
            ->set('startDate', '2026-03-01')
            ->set('endDate', '2026-03-31');

        $this->assertEqualsWithDelta(60.0, (float) $analytics->get('totalInventoryValue'), 0.01);
        $this->assertSame(0, (int) $analytics->get('outOfStockProducts'));
    }
}
