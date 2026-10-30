<?php

namespace Tests\Feature;

use App\Livewire\Pages\Orders;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\MobileMoneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Adding items to, editing and closing an order belong to admins and managers (order_debts ->
 * modify) and to the waiter who took the order. Other waiters and bartenders cannot.
 */
class OrderCloseRightsTest extends TestCase
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

    /** @param array<string, list<string>> $grants module => actions */
    private function userWith(array $grants, string $name, ?string $roleName = null): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => $roleName ?? 'Role '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

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
        return $this->userWith(['orders' => ['view', 'create'], 'order_debts' => ['view']], $name);
    }

    private function manager(): User
    {
        return $this->userWith(['orders' => ['view'], 'order_debts' => ['view', 'modify']], 'Manager One');
    }

    private function bartender(): User
    {
        return $this->userWith(['orders' => ['view', 'modify']], 'Akua');
    }

    /** A ready order taken by $by. */
    private function readyOrder(User $by, string $phone = '0241234567'): Order
    {
        $order = $this->orders->place($by, 'Kwame Mensah', $phone, [$this->beer => 2]);

        return $this->orders->markReady($order, $this->bartender());
    }

    public function test_the_waiter_who_took_the_order_can_add_items_and_close_it(): void
    {
        $ama = $this->waiter('Ama');
        $order = $this->readyOrder($ama);
        $this->actingAs($ama);

        Livewire::test(Orders::class)
            ->call('openAddItems', $order->id)
            ->call('addToCart', $this->beer)
            ->call('saveOrder')
            ->assertHasNoErrors();

        $this->assertSame('90.00', $order->fresh()->total);

        $this->orders->markReady($order->fresh(), $this->bartender());

        Livewire::test(Orders::class)
            ->call('openCloseModal', $order->id)
            ->set('paymentMethod', 'cash')
            ->call('confirmClose');

        $this->assertSame('closed', $order->fresh()->status);
        $this->assertSame($ama->id, (int) $order->fresh()->closed_by);
    }

    public function test_another_waiter_cannot_add_items_to_or_close_the_order(): void
    {
        $order = $this->readyOrder($this->waiter('Ama'));
        $this->actingAs($this->waiter('Esi'));

        Livewire::test(Orders::class)->call('openAddItems', $order->id)->assertForbidden();
        Livewire::test(Orders::class)->call('openCloseModal', $order->id)->assertForbidden();

        $this->assertSame('ready', $order->fresh()->status);
        $this->assertSame('60.00', $order->fresh()->total);
    }

    public function test_the_board_only_offers_add_items_and_close_tab_to_people_who_may_use_them(): void
    {
        $ama = $this->waiter('Ama');
        $this->readyOrder($ama);

        $this->actingAs($ama);
        Livewire::test(Orders::class)->assertSee('Close Tab');

        $this->actingAs($this->waiter('Esi'));
        Livewire::test(Orders::class)->assertSee('Kwame Mensah')->assertDontSee('Close Tab')->assertDontSee('+ Add Items');

        $this->actingAs($this->manager());
        Livewire::test(Orders::class)->assertSee('Close Tab');
    }

    public function test_a_manager_can_add_items_to_and_close_any_order(): void
    {
        $order = $this->readyOrder($this->waiter('Ama'));
        $this->actingAs($this->manager());

        Livewire::test(Orders::class)
            ->call('openAddItems', $order->id)
            ->call('addToCart', $this->beer)
            ->call('saveOrder')
            ->assertHasNoErrors();

        $this->assertSame('90.00', $order->fresh()->total);

        $this->orders->markReady($order->fresh(), $this->bartender());

        Livewire::test(Orders::class)
            ->call('openCloseModal', $order->id)
            ->set('paymentMethod', 'cash')
            ->call('confirmClose');

        $this->assertSame('closed', $order->fresh()->status);
    }

    public function test_a_bartender_cannot_close_or_add_to_an_order(): void
    {
        $order = $this->readyOrder($this->waiter('Ama'));
        $this->actingAs($this->bartender());

        Livewire::test(Orders::class)->call('openAddItems', $order->id)->assertForbidden();
        Livewire::test(Orders::class)->call('openCloseModal', $order->id)->assertForbidden();
    }

    public function test_when_the_creator_is_gone_only_managers_can_handle_the_order(): void
    {
        $ama = $this->waiter('Ama');
        $order = $this->readyOrder($ama);
        $order->update(['taken_by' => null]);

        $this->assertFalse($order->fresh()->canBeManagedBy($ama));
        $this->assertTrue($order->fresh()->canBeManagedBy($this->manager()));
    }

    public function test_the_services_refuse_everyone_else_too(): void
    {
        $ama = $this->waiter('Ama');
        $esi = $this->waiter('Esi');
        $order = $this->orders->place($ama, 'Kwame Mensah', '0241234567', [$this->beer => 2]);

        foreach ([
            fn () => $this->orders->addItems($order, $esi, [$this->beer => 1]),
            fn () => $this->orders->setItemQuantity($order, $esi, $order->items()->value('id'), 5),
            fn () => $this->orders->closeWithCash($this->orders->markReady($order, $this->bartender()), $esi),
            fn () => app(MobileMoneyService::class)->start($order->fresh(), $esi),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Another waiter must not be able to do this.');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('Only the waiter who took this order, a manager or an admin', $e->getMessage());
            }
        }

        $order->refresh();
        $this->assertSame('60.00', $order->total);
        $this->assertSame(1, $order->items()->count());
        $this->assertTrue($order->isOpen());

        // The owner and a manager still can.
        $this->orders->addItems($order, $ama, [$this->beer => 1]);
        $this->orders->addItems($order, $this->manager(), [$this->beer => 1]);
        $this->assertSame('120.00', $order->fresh()->total);
    }

    public function test_a_repeat_customer_with_another_waiters_tab_is_not_merged_or_split(): void
    {
        $ama = $this->waiter('Ama');
        $this->orders->place($ama, 'Kwame Mensah', '0241234567', [$this->beer => 2]);

        try {
            $this->orders->placeOrAddToTab($this->waiter('Esi'), 'Kwame Mensah', '024 123 4567', [$this->beer => 1]);
            $this->fail('Esi must not be able to add to Ama\'s tab.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('taken by Ama', $e->getMessage());
        }

        $this->assertSame(1, Order::count());

        [$order, $merged] = $this->orders->placeOrAddToTab($this->manager(), 'Kwame Mensah', '024 123 4567', [$this->beer => 1]);
        $this->assertTrue($merged);
        $this->assertSame('90.00', $order->total);
    }

    public function test_the_take_order_screen_warns_a_waiter_whose_customer_has_another_waiters_tab(): void
    {
        $this->orders->place($this->waiter('Ama'), 'Kwame Mensah', '0241234567', [$this->beer => 2]);
        $this->actingAs($this->waiter('Esi'));

        Livewire::test(Orders::class)
            ->call('openTakeOrder')
            ->set('customerPhone', '0241234567')
            ->assertSee('taken by Ama')
            ->assertSee('Only Ama, a manager or an admin can add to it');
    }

    public function test_managers_and_admins_get_the_right_by_migration_and_nobody_else_does(): void
    {
        $waiterRole = DB::table('roles')->where('name', 'Waiter')->value('id'); // a starter role created by the orders migration
        $managerRole = DB::table('roles')->insertGetId(['name' => 'Manager', 'created_at' => now(), 'updated_at' => now()]);
        $adminRole = DB::table('roles')->insertGetId(['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
        $stockRole = DB::table('roles')->insertGetId(['name' => 'Stock Manager', 'created_at' => now(), 'updated_at' => now()]);

        // A manager that already has the (all false) rows the earlier orders migration creates.
        $debts = DB::table('modules')->where('name', 'order_debts')->value('id');
        DB::table('permissions')->insert([
            'role_id' => $managerRole, 'module_id' => $debts, 'can_view' => false, 'can_create' => false,
            'can_modify' => false, 'can_delete' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_11_02_100100_grant_order_close_rights_to_managers.php');
        $migration->up();
        $migration->up(); // running it again changes nothing

        $can = fn (int $role, string $module, string $column) => (bool) DB::table('permissions')
            ->where('role_id', $role)->where('module_id', DB::table('modules')->where('name', $module)->value('id'))->value($column);

        foreach ([$managerRole, $adminRole] as $role) {
            $this->assertTrue($can($role, 'order_debts', 'can_modify'));
            $this->assertTrue($can($role, 'order_debts', 'can_view'));
            $this->assertTrue($can($role, 'orders', 'can_view'));
            $this->assertFalse($can($role, 'orders', 'can_create'), 'managers do not become waiters');
            $this->assertFalse($can($role, 'order_debts', 'can_delete'));
        }

        foreach ([$waiterRole, $stockRole] as $role) {
            $this->assertFalse($can($role, 'order_debts', 'can_modify'));
        }

        $this->assertSame(2, DB::table('permissions')->where('module_id', $debts)->whereIn('role_id', [$managerRole, $adminRole])->count());
    }
}
