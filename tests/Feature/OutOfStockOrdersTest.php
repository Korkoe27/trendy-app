<?php

namespace Tests\Feature;

use App\Livewire\Pages\Orders;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** A drink whose stock is zero cannot be ordered. Only drinks have stock, so food and games never run out. */
class OutOfStockOrdersTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private int $water;

    private int $jollof;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = app(OrderService::class);
        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $food = DB::table('categories')->insertGetId(['name' => 'food', 'created_at' => now(), 'updated_at' => now()]);

        $this->beer = $this->product($drinks, 'Club Beer', 30);
        $this->water = $this->product($drinks, 'Voltic Water', 5);
        $this->jollof = $this->product($food, 'Jollof Rice', 75); // no stock rows at all, and never needs any

        $this->stock($this->beer, 24);
        $this->stock($this->water, 12);
    }

    private function product(int $categoryId, string $name, float $price): int
    {
        return DB::table('products')->insertGetId(['name' => $name, 'category_id' => $categoryId, 'selling_price' => $price, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function stock(int $productId, int $units, ?string $at = null): void
    {
        DB::table('stocks')->insert(['product_id' => $productId, 'total_units' => $units, 'created_at' => $at ?? now(), 'updated_at' => $at ?? now()]);
    }

    private function waiter(): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        DB::table('permissions')->insert([
            'role_id' => $roleId, 'module_id' => DB::table('modules')->where('name', 'orders')->value('id'),
            'can_view' => true, 'can_create' => true, 'can_modify' => false, 'can_delete' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::forceCreate([
            'name' => 'Garnet', 'username' => 'garnet'.uniqid(), 'phone' => '+23324'.random_int(1000000, 9999999),
            'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false,
        ]);
    }

    public function test_a_drink_with_no_units_left_cannot_be_ordered(): void
    {
        $this->stock($this->beer, 0, now()->addMinute()->toDateTimeString()); // the latest count says none left

        try {
            $this->orders->place($this->waiter(), 'Kwame', '0241234567', [$this->beer => 1]);
            $this->fail('An out-of-stock drink must be refused.');
        } catch (\DomainException $e) {
            $this->assertSame('Club Beer is out of stock.', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
    }

    public function test_one_sold_out_item_stops_the_whole_order_and_leaves_nothing_behind(): void
    {
        $this->stock($this->beer, 0, now()->addMinute()->toDateTimeString());

        $this->expectException(\DomainException::class);

        try {
            $this->orders->place($this->waiter(), 'Kwame', '0241234567', [$this->water => 2, $this->beer => 1]);
        } finally {
            $this->assertSame(0, Order::count());
            $this->assertSame(0, DB::table('order_items')->count());
        }
    }

    public function test_a_drink_that_was_never_stocked_counts_as_out_of_stock(): void
    {
        $drinks = DB::table('categories')->where('name', 'drinks')->value('id');
        $new = $this->product($drinks, 'Heineken', 20);

        $this->expectExceptionMessage('Heineken is out of stock.');
        $this->orders->place($this->waiter(), 'Kwame', '0241234567', [$new => 1]);
    }

    public function test_the_latest_stock_figure_decides_so_restocking_makes_it_orderable_again(): void
    {
        $this->stock($this->beer, 0, now()->addMinute()->toDateTimeString());
        $this->assertTrue(Product::with(['category', 'currentStock'])->find($this->beer)->isOutOfStock());

        $this->stock($this->beer, 48, now()->addMinutes(2)->toDateTimeString());
        $this->assertFalse(Product::with(['category', 'currentStock'])->find($this->beer)->isOutOfStock());

        $order = $this->orders->place($this->waiter(), 'Kwame', '0241234567', [$this->beer => 1]);

        $this->assertSame(1, $order->items()->count());
    }

    public function test_food_and_other_non_drinks_are_never_out_of_stock(): void
    {
        $order = $this->orders->place($this->waiter(), 'Kwame', '0241234567', [$this->jollof => 2]);

        $this->assertSame(150.0, (float) $order->total);
    }

    public function test_adding_items_to_an_open_tab_is_refused_for_a_sold_out_drink(): void
    {
        $waiter = $this->waiter();
        $tab = $this->orders->place($waiter, 'Kwame', '0241234567', [$this->beer => 1]);

        $this->stock($this->beer, 0, now()->addMinute()->toDateTimeString());

        foreach ([
            fn () => $this->orders->addItems($tab, $waiter, [$this->beer => 1]),
            fn () => $this->orders->placeOrAddToTab($waiter, 'Kwame', '0241234567', [$this->beer => 1]),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A sold-out drink must not be added to a tab.');
            } catch (\DomainException $e) {
                $this->assertSame('Club Beer is out of stock.', $e->getMessage());
            }
        }

        $this->assertSame(30.0, (float) $tab->fresh()->total, 'the tab keeps what was already ordered');
        $this->assertSame(1, $tab->items()->count());
    }

    public function test_a_sold_out_drink_is_not_in_the_order_list_but_the_rest_are(): void
    {
        $this->stock($this->beer, 0, now()->addMinute()->toDateTimeString());
        $this->actingAs($this->waiter());

        $screen = Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->assertDontSee('Club Beer')
            ->assertSee('Voltic Water')
            ->assertSee('Jollof Rice');

        // Searching for it finds nothing, and a crafted call cannot put it in the cart.
        $screen->set('productSearch', 'club')->assertSee('No items match.');

        $screen->set('productSearch', '')
            ->call('addToCart', $this->beer)
            ->assertHasErrors('cart')
            ->assertSet('cart', []);

        // Items that are still in stock work as normal.
        $screen->call('addToCart', $this->water)
            ->assertSet('cart', [$this->water => 1]);
    }

    public function test_a_category_with_nothing_orderable_has_no_chip(): void
    {
        $extra = DB::table('categories')->insertGetId(['name' => 'snacks', 'created_at' => now(), 'updated_at' => now()]);
        $this->product($extra, 'Groundnuts', 3);
        DB::table('products')->where('name', 'Groundnuts')->update(['sold_out_at' => now()]);
        $this->actingAs($this->waiter());

        Livewire::test(Orders::class)->call('openTakeOrder')->assertDontSee('snacks')->assertSee('drinks');
    }

    public function test_an_item_that_runs_out_while_in_the_cart_blocks_placing_the_order(): void
    {
        $this->actingAs($this->waiter());

        $screen = Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerName', 'Kwame')
            ->set('customerPhone', '0241234567')
            ->call('addToCart', $this->beer)
            ->call('addToCart', $this->water);

        $this->stock($this->beer, 0, now()->addMinute()->toDateTimeString()); // sold out after it was added

        $screen->call('$refresh')
            ->assertSeeHtml('data-testid="cart-out-of-stock"')
            ->call('saveOrder')
            ->assertHasErrors('cart');

        $this->assertSame(0, Order::count());

        $screen->call('removeFromCart', $this->beer)->call('saveOrder')->assertHasNoErrors();

        $this->assertSame(1, Order::count());
    }

    // ------------------------------------------------------------------ switching products on and off during service

    private function bartender(): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        DB::table('permissions')->insert([
            'role_id' => $roleId, 'module_id' => DB::table('modules')->where('name', 'orders')->value('id'),
            'can_view' => true, 'can_create' => false, 'can_modify' => true, 'can_delete' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::forceCreate([
            'name' => 'Akua', 'username' => 'akua'.uniqid(), 'phone' => '+23324'.random_int(1000000, 9999999),
            'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false,
        ]);
    }

    private function manager(): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        foreach (['orders' => [true, false], 'order_debts' => [true, true]] as $module => [$view, $modify]) {
            DB::table('permissions')->insert([
                'role_id' => $roleId, 'module_id' => DB::table('modules')->where('name', $module)->value('id'),
                'can_view' => $view, 'can_create' => false, 'can_modify' => $modify, 'can_delete' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return User::forceCreate([
            'name' => 'Boss', 'username' => 'boss'.uniqid(), 'phone' => '+23324'.random_int(1000000, 9999999),
            'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false,
        ]);
    }

    public function test_the_bartender_switches_a_product_off_and_it_cannot_be_ordered(): void
    {
        $bartender = $this->bartender();
        $product = Product::find($this->water);

        $this->orders->setAvailability($bartender, $product, false);

        $this->assertTrue($product->fresh()->isSoldOut());
        $this->assertSame('Akua', $product->fresh()->sold_out_by_name);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'product_switched_off', 'user_id' => $bartender->id]);

        try {
            $this->orders->place($this->waiter(), 'Kwame', '0241234567', [$this->beer => 1, $this->water => 1]);
            $this->fail('A switched-off product must be refused.');
        } catch (\DomainException $e) {
            $this->assertSame('Voltic Water is out of stock.', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $this->orders->place($this->waiter(), 'Kwame', '0241234567', [$this->beer => 1])->items()->count());
    }

    public function test_switching_it_back_on_makes_it_orderable_and_clears_who_did_it(): void
    {
        $this->orders->setAvailability($this->manager(), Product::find($this->water), false);
        $this->orders->setAvailability($this->bartender(), Product::find($this->water), true);

        $product = Product::find($this->water);
        $this->assertFalse($product->isSoldOut());
        $this->assertNull($product->sold_out_by_name);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'product_switched_on']);

        $this->assertSame(1, $this->orders->place($this->waiter(), 'Kwame', '0241234567', [$this->water => 1])->items()->count());
    }

    public function test_food_can_be_switched_off_too(): void
    {
        $this->orders->setAvailability($this->bartender(), Product::find($this->jollof), false);

        $this->expectExceptionMessage('Jollof Rice is out of stock.');
        $this->orders->place($this->waiter(), 'Kwame', '0241234567', [$this->jollof => 1]);
    }

    public function test_a_drink_with_no_stock_cannot_be_switched_on(): void
    {
        $this->stock($this->beer, 0, now()->addMinute()->toDateTimeString());

        $this->expectExceptionMessage('Club Beer has no stock. Restock it on the Stocks page first.');
        $this->orders->setAvailability($this->bartender(), Product::find($this->beer), true);
    }

    public function test_a_waiter_cannot_switch_products(): void
    {
        $this->expectException(\DomainException::class);
        $this->orders->setAvailability($this->waiter(), Product::find($this->water), false);
    }

    public function test_the_availability_window_is_for_the_bar_and_managers_and_toggles_a_product(): void
    {
        $this->actingAs($this->waiter());
        Livewire::test(Orders::class)
            ->assertDontSeeHtml('data-testid="open-availability"')
            ->call('openAvailability')->assertForbidden();
        Livewire::test(Orders::class)->call('toggleAvailability', $this->water)->assertForbidden();

        foreach ([$this->bartender(), $this->manager()] as $user) {
            DB::table('products')->update(['sold_out_at' => null, 'sold_out_by_name' => null]);
            $this->actingAs($user);

            $screen = Livewire::test(Orders::class)
                ->assertSeeHtml('data-testid="open-availability"')
                ->call('openAvailability')
                ->assertSeeHtml('data-testid="availability-modal"')
                ->assertSee('Voltic Water')
                ->call('toggleAvailability', $this->water);

            $this->assertTrue(Product::find($this->water)->isSoldOut());
            $screen->assertSee('Off since');

            $screen->call('toggleAvailability', $this->water);
            $this->assertFalse(Product::find($this->water)->isSoldOut());
        }
    }

    public function test_the_window_shows_a_drink_with_no_stock_as_needing_a_restock_and_refuses_to_switch_it_on(): void
    {
        $this->stock($this->beer, 0, now()->addMinute()->toDateTimeString());
        $this->actingAs($this->bartender());

        Livewire::test(Orders::class)
            ->call('openAvailability')
            ->assertSee('No stock, restock it first')
            ->call('toggleAvailability', $this->beer)
            ->assertHasErrors('availability');

        $this->assertFalse(Product::find($this->beer)->isSoldOut());
    }

    public function test_the_window_search_narrows_the_list(): void
    {
        $this->actingAs($this->bartender());

        Livewire::test(Orders::class)
            ->call('openAvailability')
            ->set('availabilitySearch', 'jollof')
            ->assertSee('Jollof Rice')
            ->assertDontSee('Voltic Water');
    }
}
