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

class OrdersPageTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private int $jollof;

    protected function setUp(): void
    {
        parent::setUp();

        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $food = DB::table('categories')->insertGetId(['name' => 'food', 'created_at' => now(), 'updated_at' => now()]);

        $this->beer = DB::table('products')->insertGetId(['name' => 'Club Beer', 'category_id' => $drinks, 'selling_price' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->jollof = DB::table('products')->insertGetId(['name' => 'Jollof Rice', 'category_id' => $food, 'selling_price' => 75, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, list<string>> $grants module => actions */
    private function userWith(array $grants, string $name = 'Garnet'): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        foreach ($grants as $module => $actions) {
            DB::table('permissions')->insert([
                'role_id' => $roleId,
                'module_id' => DB::table('modules')->where('name', $module)->value('id'),
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

    private function waiter(): User
    {
        return $this->userWith(['orders' => ['view', 'create'], 'order_debts' => ['view']], 'Garnet');
    }

    private function bartender(): User
    {
        return $this->userWith(['orders' => ['view', 'modify']], 'Akua');
    }

    private function order(User $by, ?array $items = null): Order
    {
        return app(OrderService::class)->place($by, 'Kwame Mensah', '0241234567', $items ?? [$this->beer => 2]);
    }

    public function test_a_returning_customer_gets_a_notice_a_prefilled_name_and_the_items_join_their_tab(): void
    {
        $waiter = $this->waiter();
        $tab = $this->order($waiter); // Kwame Mensah, 0241234567, 2 beers = 60

        $this->actingAs($waiter);

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->assertDontSee('already has an open tab')
            ->set('customerPhone', '+233 24 123 4567')
            ->assertSet('customerName', 'Kwame Mensah')
            ->assertSee('already has an open tab')
            ->assertSee($tab->code)
            ->assertSee('GH₵ 60.00')
            ->call('addToCart', $this->jollof)
            ->assertSee('ADD TO TAB')
            ->call('saveOrder')
            ->assertHasNoErrors()
            ->assertSet('showOrderModal', false)
            ->assertSee("Added to {$tab->code}")
            ->assertSee('tab now ₵135.00');

        $this->assertSame(1, Order::count());
        $this->assertSame('135.00', $tab->fresh()->total);
        $this->assertSame('pending', $tab->fresh()->status);
    }

    public function test_a_typed_name_is_not_overwritten_and_a_new_customer_gets_a_normal_order(): void
    {
        $waiter = $this->waiter();
        $this->order($waiter);

        $this->actingAs($waiter);

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerName', 'Ama')
            ->set('customerPhone', '0249999999')
            ->assertSet('customerName', 'Ama')
            ->assertDontSee('already has an open tab')
            ->assertSee('PLACE ORDER')
            ->call('addToCart', $this->beer)
            ->call('saveOrder')
            ->assertHasNoErrors()
            ->assertSee('placed for Ama');

        $this->assertSame(2, Order::count());
    }

    public function test_adding_items_to_an_order_shows_no_returning_customer_notice(): void
    {
        $waiter = $this->waiter();
        $tab = $this->order($waiter);

        $this->actingAs($waiter);

        Livewire::test(Orders::class)
            ->call('openAddItems', $tab->id)
            ->assertDontSee('already has an open tab')
            ->assertSee('ADD TO ORDER');
    }

    public function test_a_completed_order_can_be_opened_to_see_everything_on_it(): void
    {
        $waiter = $this->waiter();
        $bartender = $this->bartender();
        $orders = app(OrderService::class);

        $paid = $orders->place($waiter, 'Kwame Mensah', '0241234567', [$this->beer => 2, $this->jollof => 1, ], 'Table 4');
        $orders->markReady($paid, $bartender);
        $orders->closeWithCash($paid, $waiter);

        $cancelled = $orders->place($waiter, 'Ama Serwaa', '0249999999', [$this->beer => 1]);
        $orders->cancel($cancelled, $waiter, 'Customer left');

        $this->actingAs($waiter);

        Livewire::test(Orders::class)
            ->assertSee('View')
            ->call('openViewOrder', $paid->id)
            ->assertSet('showViewModal', true)
            ->assertSee('2x Club Beer')
            ->assertSee('1x Jollof Rice')
            ->assertSee('GH₵ 135.00')
            ->assertSee('Table 4')
            ->assertSee('Cash')
            ->assertSee('Marked ready')
            ->call('closeViewModal')
            ->assertSet('showViewModal', false)
            ->call('openViewOrder', $cancelled->id)
            ->assertSee('Customer left')
            ->assertSee('Cancelled');
    }

    public function test_viewing_an_order_needs_the_orders_view_permission(): void
    {
        $order = $this->order($this->waiter());

        $this->actingAs($this->userWith([]));

        Livewire::test(Orders::class)->call('openViewOrder', $order->id)->assertForbidden();
    }

    public function test_route_needs_the_orders_view_permission(): void
    {
        $this->actingAs($this->userWith([]))->get(route('orders'))->assertForbidden();
        $this->actingAs($this->waiter())->get(route('orders'))->assertOk()->assertSee('Orders');
    }

    public function test_starter_roles_from_the_migration_have_the_intended_access(): void
    {
        $waiter = User::forceCreate(['name' => 'W', 'username' => 'w', 'phone' => '0241000001', 'role_id' => DB::table('roles')->where('name', 'Waiter')->value('id'), 'password' => '482913', 'must_change_password' => false]);
        $bartender = User::forceCreate(['name' => 'B', 'username' => 'b', 'phone' => '0241000002', 'role_id' => DB::table('roles')->where('name', 'Bartender')->value('id'), 'password' => '482913', 'must_change_password' => false]);

        $this->assertTrue($waiter->hasPermission('view', 'orders'));
        $this->assertTrue($waiter->hasPermission('create', 'orders'));
        $this->assertFalse($waiter->hasPermission('modify', 'orders'));
        $this->assertTrue($waiter->hasPermission('view', 'order_debts'));

        $this->assertTrue($bartender->hasPermission('view', 'orders'));
        $this->assertTrue($bartender->hasPermission('modify', 'orders'));
        $this->assertFalse($bartender->hasPermission('create', 'orders'));
        $this->assertFalse($bartender->hasPermission('view', 'order_debts'));
    }

    public function test_the_full_order_flow_from_waiter_to_bartender_and_back(): void
    {
        $waiter = $this->waiter();
        $bartender = $this->bartender();

        // Waiter takes the order.
        $this->actingAs($waiter);
        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerName', 'Kwame Mensah')
            ->set('customerPhone', '024 123 4567')
            ->call('addToCart', $this->beer)
            ->call('addToCart', $this->beer)
            ->call('addToCart', $this->jollof)
            ->assertSee('GH₵ 135.00')
            ->call('saveOrder')
            ->assertHasNoErrors()
            ->assertSet('showOrderModal', false)
            ->assertSee('Kwame Mensah')
            ->assertSee('024 •• 4567');

        $order = Order::firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('135.00', $order->total);
        $this->assertSame($waiter->id, $order->taken_by);

        // Bartender sees it and marks it ready.
        $this->actingAs($bartender);
        Livewire::test(Orders::class)
            ->assertSee('Kwame Mensah')
            ->call('markReady', $order->id);
        $this->assertSame('ready', $order->fresh()->status);

        // Waiter serves it, adds a round, it goes back to the bartender, then the tab is closed with cash.
        $this->actingAs($waiter);
        $component = Livewire::test(Orders::class)->call('serve', $order->id);
        $this->assertSame('served', $order->fresh()->status);

        $component->call('openAddItems', $order->id)
            ->assertSet('customerName', 'Kwame Mensah')
            ->call('addToCart', $this->jollof)
            ->call('saveOrder')
            ->assertHasNoErrors();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('210.00', $order->fresh()->total);

        $this->actingAs($bartender);
        Livewire::test(Orders::class)->call('markReady', $order->id);

        $this->actingAs($waiter);
        Livewire::test(Orders::class)
            ->call('openCloseModal', $order->id)
            ->assertSee('GH₵ 210.00')
            ->set('paymentMethod', 'cash')
            ->call('confirmClose')
            ->assertHasNoErrors()
            ->assertSet('showCloseModal', false);

        $order->refresh();
        $this->assertSame('closed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash', $order->payment_method);
    }

    public function test_a_bartender_cannot_take_orders_or_close_tabs(): void
    {
        $order = $this->order($this->waiter());
        $this->actingAs($this->bartender());

        Livewire::test(Orders::class)->call('openTakeOrder')->assertForbidden();
        Livewire::test(Orders::class)->call('addToCart', $this->beer)->assertForbidden();
        Livewire::test(Orders::class)->call('openCloseModal', $order->id)->assertForbidden();
    }

    public function test_a_waiter_cannot_mark_orders_ready_or_cancel_them(): void
    {
        $waiter = $this->waiter();
        $order = $this->order($waiter);
        $this->actingAs($waiter);

        Livewire::test(Orders::class)->call('markReady', $order->id)->assertForbidden();
        Livewire::test(Orders::class)->call('openCancelModal', $order->id)->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_action_buttons_follow_permissions(): void
    {
        $waiter = $this->waiter();
        $this->order($waiter);

        $this->actingAs($waiter);
        Livewire::test(Orders::class)->assertSee('TAKE ORDER')->assertSee('Edit')->assertDontSee('Order Ready');

        $this->actingAs($this->bartender());
        Livewire::test(Orders::class)->assertDontSee('TAKE ORDER')->assertSee('Order Ready');
    }

    public function test_placing_requires_customer_details_and_items(): void
    {
        $this->actingAs($this->waiter());

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->call('saveOrder')
            ->assertHasErrors(['customerName', 'customerPhone']);

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerName', 'Kwame')
            ->set('customerPhone', '123')
            ->call('addToCart', $this->beer)
            ->call('saveOrder')
            ->assertHasErrors(['customerPhone']);

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerName', 'Kwame')
            ->set('customerPhone', '0241234567')
            ->call('saveOrder')
            ->assertHasErrors(['cart']);

        $this->assertSame(0, Order::count());
    }

    public function test_the_cart_ignores_prices_sent_from_the_browser_because_the_server_prices_it(): void
    {
        $this->actingAs($this->waiter());

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerName', 'Kwame')
            ->set('customerPhone', '0241234567')
            ->set('cart', [$this->beer => 1])
            ->call('saveOrder');

        $this->assertSame('30.00', Order::firstOrFail()->total);
    }

    public function test_mobile_money_is_shown_but_switched_off(): void
    {
        $waiter = $this->waiter();
        $order = app(OrderService::class)->markReady($this->order($waiter), $this->bartender());
        $this->actingAs($waiter);

        Livewire::test(Orders::class)
            ->call('openCloseModal', $order->id)
            ->assertSee('Pay via Mobile Money')
            ->assertSee('Coming soon')
            ->set('paymentMethod', 'momo')
            ->call('confirmClose')
            ->assertHasErrors(['paymentMethod']);

        $this->assertSame('ready', $order->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_closing_a_pending_order_is_refused(): void
    {
        $waiter = $this->waiter();
        $order = $this->order($waiter);
        $this->actingAs($waiter);

        Livewire::test(Orders::class)
            ->call('openCloseModal', $order->id)
            ->call('confirmClose')
            ->assertHasErrors(['paymentMethod']);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_the_same_tab_cannot_be_closed_from_two_screens(): void
    {
        $waiter = $this->waiter();
        $order = app(OrderService::class)->markReady($this->order($waiter), $this->bartender());
        $this->actingAs($waiter);

        $first = Livewire::test(Orders::class)->call('openCloseModal', $order->id);
        $second = Livewire::test(Orders::class)->call('openCloseModal', $order->id);

        $first->call('confirmClose')->assertHasNoErrors();
        $second->call('confirmClose')->assertHasErrors(['paymentMethod']);

        $this->assertSame(1, DB::table('activity_logs')->where('action_type', 'order_paid')->count());
    }

    public function test_pending_items_can_be_edited_but_prepared_ones_are_locked(): void
    {
        $waiter = $this->waiter();
        $bartender = $this->bartender();
        $order = $this->order($waiter, [$this->beer => 2]);
        $service = app(OrderService::class);
        $service->markReady($order, $bartender);
        $service->addItems($order, $waiter, [$this->jollof => 1]);

        $this->actingAs($waiter);
        $order->load('items');
        $locked = $order->items->firstWhere('status', 'ready');
        $waiting = $order->items->firstWhere('status', 'pending');

        Livewire::test(Orders::class)
            ->call('openAddItems', $order->id)
            ->assertSee('Locked')
            ->call('changeItemQuantity', $locked->id, 5)
            ->assertHasErrors(['cart'])
            ->call('changeItemQuantity', $waiting->id, 3)
            ->assertHasNoErrors();

        $this->assertSame(3, $waiting->fresh()->quantity);
        $this->assertSame(2, $locked->fresh()->quantity);
    }

    public function test_cancelling_needs_a_reason_and_moves_the_order_to_history(): void
    {
        $manager = $this->userWith(['orders' => ['view', 'create', 'modify', 'delete']], 'Manager');
        $order = $this->order($manager);
        $this->actingAs($manager);

        Livewire::test(Orders::class)
            ->call('openCancelModal', $order->id)
            ->call('confirmCancel')
            ->assertHasErrors(['cancelReason'])
            ->set('cancelReason', 'Customer left')
            ->call('confirmCancel')
            ->assertHasNoErrors()
            ->assertSee('Cancelled');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_search_filters_the_board_by_name_phone_and_code(): void
    {
        $waiter = $this->waiter();
        $service = app(OrderService::class);
        $service->place($waiter, 'Ama Mensah', '0245550001', [$this->beer => 1]);
        $service->place($waiter, 'Kofi Boahen', '0205550002', [$this->beer => 1]);

        $this->actingAs($waiter);

        Livewire::test(Orders::class)->assertSee('Ama Mensah')->assertSee('Kofi Boahen')
            ->set('searchTerm', 'kofi')->assertSee('Kofi Boahen')->assertDontSee('Ama Mensah')
            ->set('searchTerm', '024 555')->assertSee('Ama Mensah')->assertDontSee('Kofi Boahen')
            ->set('searchTerm', 'ORD-0002')->assertSee('Kofi Boahen')->assertDontSee('Ama Mensah');
    }

    public function test_completed_orders_are_listed_with_their_payment_method(): void
    {
        $waiter = $this->waiter();
        $service = app(OrderService::class);
        $order = $service->markReady($this->order($waiter), $this->bartender());
        $service->closeWithCash($order, $waiter);

        $this->actingAs($waiter);

        Livewire::test(Orders::class)->assertSee('Completed orders')->assertSee('Cash')->assertSee('Paid')->assertSee('60.00');
    }

    public function test_order_ids_held_by_the_component_cannot_be_changed_from_the_browser(): void
    {
        $waiter = $this->waiter();
        $order = $this->order($waiter);
        $this->actingAs($waiter);

        foreach (['editingOrderId', 'closingOrderId', 'cancellingOrderId'] as $property) {
            try {
                Livewire::test(Orders::class)->set($property, $order->id);
                $this->fail("{$property} should be locked");
            } catch (\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_search_ignores_case_so_it_also_works_on_postgresql(): void
    {
        $waiter = $this->waiter();
        app(OrderService::class)->place($waiter, 'Kofi Boahen', '0205550002', [$this->beer => 1]);
        $this->actingAs($waiter);

        Livewire::test(Orders::class)->set('searchTerm', 'KOFI')->assertSee('Kofi Boahen')
            ->set('searchTerm', 'ord-0001')->assertSee('Kofi Boahen');

        $closed = app(OrderService::class)->markReady($this->order($waiter), $this->bartender());
        app(OrderService::class)->closeWithCash($closed, $waiter);

        Livewire::test(Orders::class)->set('searchTerm', 'KWAME')->assertSee('Completed orders')->assertSee('Kwame Mensah');
    }
}
