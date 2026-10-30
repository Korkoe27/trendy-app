<?php

namespace Tests\Feature;

use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\Orders;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A waiter only sees the orders they took. Managers/admins (order_debts, modify) and the
 * bartender (orders, modify) see every order.
 */
class WaiterSeesOwnOrdersTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = app(OrderService::class);
        $category = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $this->beer = DB::table('products')->insertGetId(['name' => 'Club Beer', 'category_id' => $category, 'selling_price' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

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

    private function waiter(string $name): User
    {
        return $this->userWith(['orders' => ['view', 'create', 'delete'], 'order_debts' => ['view']], $name);
    }

    private function twoOrders(): array
    {
        $ama = $this->waiter('Ama');
        $kofi = $this->waiter('Kofi');

        return [
            $ama, $kofi,
            $this->orders->place($ama, 'Amas Customer', '0241111111', [$this->beer => 1]),
            $this->orders->place($kofi, 'Kofis Customer', '0242222222', [$this->beer => 2]),
        ];
    }

    public function test_a_waiter_sees_only_their_own_orders_on_the_board(): void
    {
        [$ama, , $amaOrder, $kofiOrder] = $this->twoOrders();
        $this->actingAs($ama);

        Livewire::test(Orders::class)
            ->assertSee($amaOrder->code)
            ->assertSee('Amas Customer')
            ->assertDontSee($kofiOrder->code)
            ->assertDontSee('Kofis Customer');
    }

    public function test_a_waiter_does_not_see_other_waiters_orders_in_history_or_search(): void
    {
        [$ama, $kofi, $amaOrder, $kofiOrder] = $this->twoOrders();
        $bar = $this->userWith(['orders' => ['view', 'modify']], 'Akua');
        $mgr = $this->userWith(['orders' => ['view'], 'order_debts' => ['view', 'modify']], 'Boss');

        foreach ([$amaOrder, $kofiOrder] as $o) {
            $this->orders->markReady($o, $bar);
            $this->orders->closeWithCash($o->fresh(), $mgr);
        }

        $this->actingAs($ama);

        Livewire::test(Orders::class)
            ->assertSee('Amas Customer')
            ->assertDontSee('Kofis Customer')
            ->set('searchTerm', 'Kofis')
            ->assertDontSee('Kofis Customer');
    }

    public function test_managers_and_the_bartender_see_every_order(): void
    {
        [, , $amaOrder, $kofiOrder] = $this->twoOrders();

        foreach ([
            $this->userWith(['orders' => ['view'], 'order_debts' => ['view', 'modify']], 'Boss'),
            $this->userWith(['orders' => ['view', 'modify']], 'Akua'),
        ] as $user) {
            $this->actingAs($user);

            Livewire::test(Orders::class)
                ->assertSee($amaOrder->code)
                ->assertSee($kofiOrder->code);
        }
    }

    public function test_a_waiter_cannot_reach_another_waiters_order_by_id(): void
    {
        [$ama, , , $kofiOrder] = $this->twoOrders();
        $this->actingAs($ama);

        Livewire::test(Orders::class)->call('openViewOrder', $kofiOrder->id)->assertForbidden();
        Livewire::test(Orders::class)->call('openCancelModal', $kofiOrder->id)->assertForbidden();
        Livewire::test(Orders::class)->call('serve', $kofiOrder->id)->assertForbidden();
        Livewire::test(Orders::class)->call('openAddItems', $kofiOrder->id)->assertForbidden();
        Livewire::test(Orders::class)->call('openCloseModal', $kofiOrder->id)->assertForbidden();

        $this->assertSame('pending', $kofiOrder->fresh()->status);
    }

    public function test_the_unpaid_tabs_card_lists_only_the_waiters_own_tabs(): void
    {
        [$ama, , $amaOrder, $kofiOrder] = $this->twoOrders();
        $this->actingAs($ama);

        $tabs = Livewire::test(Dashboard::class)->instance()->unpaidTabs;

        $this->assertSame([$amaOrder->id], $tabs->pluck('id')->all());

        $this->actingAs($this->userWith(['order_debts' => ['view', 'modify']], 'Boss'));

        $this->assertEqualsCanonicalizing(
            [$amaOrder->id, $kofiOrder->id],
            Livewire::test(Dashboard::class)->instance()->unpaidTabs->pluck('id')->all()
        );
    }

    public function test_the_blocked_tab_notice_does_not_reveal_the_other_waiters_customer(): void
    {
        [$ama, , , ] = $this->twoOrders();
        $this->actingAs($ama);

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerPhone', '0242222222')
            ->assertSee('already has an open tab with another waiter')
            ->assertDontSee('Kofis Customer')
            ->assertDontSee('Kofi');
    }

    public function test_the_visibility_rule_on_the_model(): void
    {
        [$ama, $kofi, $amaOrder, $kofiOrder] = $this->twoOrders();

        $this->assertTrue($amaOrder->canBeSeenBy($ama));
        $this->assertFalse($kofiOrder->canBeSeenBy($ama));
        $this->assertSame([$amaOrder->id], Order::visibleTo($ama)->pluck('id')->all());
        $this->assertCount(0, Order::visibleTo(null)->get());
    }

    public function test_board_cards_are_compact_and_the_view_button_opens_the_details(): void
    {
        [$ama, , $amaOrder] = $this->twoOrders();
        $this->actingAs($ama);

        Livewire::test(Orders::class)
            ->assertSee($amaOrder->code)
            ->assertSee('Amas Customer')
            ->assertSee('GH₵ 30.00')
            ->assertSee('View')
            ->assertDontSee('1x Club Beer') // items and phone are not on the card
            ->assertDontSee('0241111111')
            ->call('openViewOrder', $amaOrder->id)
            ->assertSet('showViewModal', true)
            ->assertSee('1x Club Beer')
            ->assertSee('0241111111')
            ->assertSeeHtml('href="tel:+233241111111"')
            ->assertSee('Pending');
    }
}
