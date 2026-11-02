<?php

namespace Tests\Feature;

use App\Livewire\Pages\Orders;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Three workflow rules: the bartender can change an order that is still waiting to be prepared,
 * the bartender cannot serve, and a waiter works one order at a time (pending or ready orders hold
 * them until they serve).
 */
class OrderWorkflowRulesTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private int $water;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = app(OrderService::class);
        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);

        foreach (['beer' => ['Club Beer', 30], 'water' => ['Voltic Water', 5]] as $key => [$name, $price]) {
            $this->$key = DB::table('products')->insertGetId(['name' => $name, 'category_id' => $drinks, 'selling_price' => $price, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('stocks')->insert(['product_id' => $this->$key, 'total_units' => 100, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @param array<string, list<string>> $grants module => actions */
    private function userWith(array $grants, string $name): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        foreach ($grants as $module => $actions) {
            DB::table('permissions')->insert([
                'role_id' => $roleId, 'module_id' => DB::table('modules')->where('name', $module)->value('id'),
                'can_view' => in_array('view', $actions), 'can_create' => in_array('create', $actions),
                'can_modify' => in_array('modify', $actions), 'can_delete' => in_array('delete', $actions),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return User::forceCreate([
            'name' => $name, 'username' => strtolower($name).uniqid(), 'phone' => '+23324'.random_int(1000000, 9999999),
            'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false,
        ]);
    }

    private function waiter(string $name = 'Garnet'): User
    {
        return $this->userWith(['orders' => ['view', 'create']], $name);
    }

    private function bartender(): User
    {
        return $this->userWith(['orders' => ['view', 'modify']], 'Akua');
    }

    private function manager(): User
    {
        return $this->userWith(['orders' => ['view', 'create'], 'order_debts' => ['view', 'modify']], 'Boss');
    }

    private function place(User $by, string $phone = '0241234567', ?array $items = null): Order
    {
        return $this->orders->place($by, 'Customer '.substr($phone, -3), $phone, $items ?? [$this->beer => 2, $this->water => 1]);
    }

    // ------------------------------------------------------------------ 1. the bartender edits a pending order

    public function test_the_bartender_can_change_remove_and_add_items_on_a_pending_order(): void
    {
        $waiter = $this->waiter();
        $bartender = $this->bartender();
        $order = $this->place($waiter); // 2 beers + 1 water = 65

        $beerItem = $order->items->firstWhere('product_id', $this->beer);
        $waterItem = $order->items->firstWhere('product_id', $this->water);

        $this->orders->setItemQuantity($order, $bartender, $beerItem->id, 1);
        $this->assertSame(35.0, (float) $order->fresh()->total, 'quantity changed');

        $this->orders->setItemQuantity($order, $bartender, $waterItem->id, 0);
        $this->assertSame(30.0, (float) $order->fresh()->total, 'item removed');
        $this->assertSame(1, $order->items()->count());

        $this->orders->addItems($order, $bartender, [$this->water => 3]);
        $this->assertSame(45.0, (float) $order->fresh()->total, 'item added');
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'order_edit_item', 'user_id' => $bartender->id]);
    }

    public function test_the_bartender_cannot_edit_an_order_that_is_no_longer_waiting_to_be_prepared(): void
    {
        $order = $this->orders->markReady($this->place($this->waiter()), $this->bartender());
        $bartender = $this->bartender();

        foreach ([
            fn () => $this->orders->addItems($order, $bartender, [$this->water => 1]),
            fn () => $this->orders->setItemQuantity($order, $bartender, $order->items->first()->id, 1),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A ready order must not be editable by the bartender.');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('still waiting to be prepared', $e->getMessage());
            }
        }

        $this->assertSame(65.0, (float) $order->fresh()->total);
    }

    public function test_the_bartender_still_cannot_close_a_tab(): void
    {
        $order = $this->place($this->waiter());

        $this->expectException(\DomainException::class);
        $this->orders->closeWithCash($order, $this->bartender());
    }

    public function test_the_bartender_edits_through_the_board(): void
    {
        $order = $this->place($this->waiter());
        $item = $order->items->firstWhere('product_id', $this->beer);
        $this->actingAs($this->bartender());

        Livewire::test(Orders::class)
            ->assertSeeHtml('wire:click="openAddItems('.$order->id.')"')
            ->call('openAddItems', $order->id)
            ->assertSet('editingOrderId', $order->id)
            ->call('changeItemQuantity', $item->id, 1)
            ->call('addToCart', $this->water)
            ->call('saveOrder')
            ->assertHasNoErrors();

        $this->assertSame(40.0, (float) $order->fresh()->total, 'one beer, two waters');
    }

    public function test_the_bartender_has_no_edit_button_once_the_order_is_ready_and_cannot_open_it(): void
    {
        $order = $this->orders->markReady($this->place($this->waiter()), $this->bartender());
        $this->actingAs($this->bartender());

        Livewire::test(Orders::class)
            ->assertDontSeeHtml('wire:click="openAddItems('.$order->id.')"')
            ->call('openAddItems', $order->id)
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ 2. the bartender cannot serve

    public function test_the_bartender_cannot_serve_but_the_waiter_can(): void
    {
        $waiter = $this->waiter();
        $order = $this->orders->markReady($this->place($waiter), $this->bartender());

        try {
            $this->orders->serve($order, $this->bartender());
            $this->fail('The bartender must not serve.');
        } catch (\DomainException $e) {
            $this->assertSame('Only a waiter can serve an order to the customer.', $e->getMessage());
        }

        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);

        $this->assertSame(Order::STATUS_SERVED, $this->orders->serve($order, $waiter)->status);
    }

    public function test_the_serve_button_is_for_waiters_only(): void
    {
        $waiter = $this->waiter();
        $order = $this->orders->markReady($this->place($waiter), $this->bartender());

        $this->actingAs($this->bartender());
        Livewire::test(Orders::class)
            ->assertDontSeeHtml('wire:click="serve('.$order->id.')"')
            ->call('serve', $order->id)
            ->assertForbidden();
        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);

        $this->actingAs($waiter);
        Livewire::test(Orders::class)
            ->assertSeeHtml('wire:click="serve('.$order->id.')"')
            ->call('serve', $order->id);
        $this->assertSame(Order::STATUS_SERVED, $order->fresh()->status);
    }

    // ------------------------------------------------------------------ 3. a waiter works one order at a time

    public function test_a_waiter_cannot_place_another_order_while_one_is_pending_or_ready(): void
    {
        $waiter = $this->waiter();
        $bartender = $this->bartender();
        $first = $this->place($waiter);

        try {
            $this->place($waiter, '0245550000');
            $this->fail('A second order while the first is pending must be refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString("You still have {$first->code}", $e->getMessage());
            $this->assertStringContainsString('Serve it to the customer before taking another order.', $e->getMessage());
        }

        $this->orders->markReady($first, $bartender); // ready but not served yet: still held

        $this->expectException(\DomainException::class);
        $this->place($waiter, '0245550000');
    }

    public function test_serving_frees_the_waiter_and_nothing_was_left_behind_by_the_refusal(): void
    {
        $waiter = $this->waiter();
        $first = $this->place($waiter);

        try {
            $this->place($waiter, '0245550000');
        } catch (\DomainException) {
        }

        $this->assertSame(1, Order::count());

        $this->orders->serve($this->orders->markReady($first, $this->bartender()), $waiter);

        $second = $this->place($waiter, '0245550000');

        $this->assertSame(2, Order::count());
        $this->assertSame(Order::STATUS_PENDING, $second->status);
    }

    public function test_cancelled_and_closed_orders_do_not_hold_the_waiter(): void
    {
        $waiter = $this->waiter();
        $first = $this->place($waiter);
        $this->orders->cancel($first, $this->manager(), 'Left');

        $second = $this->place($waiter, '0245550000');
        $second->update(['status' => Order::STATUS_CLOSED]);

        $this->assertSame(Order::STATUS_PENDING, $this->place($waiter, '0246660000')->status);
    }

    public function test_the_rule_is_per_waiter(): void
    {
        $ama = $this->waiter('Ama');
        $kofi = $this->waiter('Kofi');
        $this->place($ama);

        $this->assertSame(Order::STATUS_PENDING, $this->place($kofi, '0245550000')->status);
    }

    public function test_managers_and_the_bartender_are_not_held_by_the_rule(): void
    {
        $manager = $this->manager();
        $this->place($manager, '0241111111');
        $this->place($manager, '0242222222');

        $this->assertSame(2, Order::where('taken_by', $manager->id)->count());
        $this->assertNull($this->orders->unservedOrderFor($manager));
        $this->assertNull($this->orders->unservedOrderFor($this->bartender()));
    }

    public function test_a_repeat_customer_still_adds_to_their_own_pending_order(): void
    {
        $waiter = $this->waiter();
        $first = $this->place($waiter);

        [$order, $merged] = $this->orders->placeOrAddToTab($waiter, 'Customer', '0241234567', [$this->water => 2]);

        $this->assertTrue($merged);
        $this->assertSame($first->id, $order->id);
        $this->assertSame(75.0, (float) $order->total);
    }

    public function test_adding_to_a_served_tab_is_refused_while_another_order_is_unserved(): void
    {
        $waiter = $this->waiter();
        $tab = $this->orders->serve($this->orders->markReady($this->place($waiter, '0241111111'), $this->bartender()), $waiter);
        $this->place($waiter, '0242222222'); // a new order, still pending

        try {
            $this->orders->addItems($tab, $waiter, [$this->water => 1]);
            $this->fail('A second piece of work must wait until the pending order is served.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Serve it to the customer', $e->getMessage());
        }

        $this->assertSame(Order::STATUS_SERVED, $tab->fresh()->status);
    }

    public function test_the_screen_disables_take_order_with_a_notice_and_refuses_to_open(): void
    {
        $waiter = $this->waiter();
        $order = $this->place($waiter);
        $this->actingAs($waiter);

        Livewire::test(Orders::class)
            ->assertSeeHtml('data-testid="unserved-order-notice"')
            ->assertSee($order->code)
            ->call('openTakeOrder')
            ->assertSet('showOrderModal', false)
            ->assertSee('Serve '.$order->code.' to the customer before taking another order.');

        $this->orders->serve($this->orders->markReady($order, $this->bartender()), $waiter);

        Livewire::test(Orders::class)
            ->assertDontSeeHtml('data-testid="unserved-order-notice"')
            ->call('openTakeOrder')
            ->assertSet('showOrderModal', true);
    }

    public function test_the_screen_does_not_show_the_notice_to_managers(): void
    {
        $manager = $this->manager();
        $this->place($manager);
        $this->actingAs($manager);

        Livewire::test(Orders::class)
            ->assertDontSeeHtml('data-testid="unserved-order-notice"')
            ->call('openTakeOrder')
            ->assertSet('showOrderModal', true);
    }

    public function test_a_submit_from_an_already_open_screen_is_refused_too(): void
    {
        $waiter = $this->waiter();
        $this->actingAs($waiter);

        $screen = Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerName', 'Esi')->set('customerPhone', '0245550000')
            ->call('addToCart', $this->beer);

        $this->place($waiter); // the waiter's other device took an order in the meantime

        $screen->call('saveOrder')->assertHasErrors('cart');

        $this->assertSame(1, Order::count());
    }
}
