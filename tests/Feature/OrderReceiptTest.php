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

class OrderReceiptTest extends TestCase
{
    use RefreshDatabase;

    private int $beer;

    protected function setUp(): void
    {
        parent::setUp();

        $drinks = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);

        $this->beer = DB::table('products')->insertGetId([
            'name' => 'Club Beer', 'category_id' => $drinks, 'selling_price' => 30,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('stocks')->insert([
            'product_id' => $this->beer, 'total_units' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, list<string>> $grants module => actions */
    private function userWith(array $grants, string $name): User
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

    private function waiter(string $name = 'Garnet'): User
    {
        return $this->userWith(['orders' => ['view', 'create']], $name);
    }

    private function manager(): User
    {
        return $this->userWith(['orders' => ['view', 'create', 'modify'], 'order_debts' => ['view', 'modify']], 'Boss');
    }

    private function placeOrder(User $by): Order
    {
        return app(OrderService::class)->place($by, 'Kwame Mensah', '0241234567', [$this->beer => 2]);
    }

    private function paidOrder(User $waiter, User $manager): Order
    {
        $orders = app(OrderService::class);
        $order = $this->placeOrder($waiter);
        $orders->markReady($order, $manager);
        $orders->closeWithCash($order, $manager);

        return $order->fresh();
    }

    public function test_a_paid_orders_receipt_is_printable(): void
    {
        $waiter = $this->waiter();
        $order = $this->paidOrder($waiter, $this->manager());

        $this->actingAs($waiter);

        $this->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('KWAME MENSAH')
            ->assertSee('CLUB BEER')
            ->assertSee('Served by GARNET')
            ->assertSee('GH₵ 60.00')
            ->assertSee('white-logo.png')
            ->assertSee('Print receipt');
    }

    public function test_an_unpaid_order_has_no_receipt(): void
    {
        $waiter = $this->waiter();
        $order = $this->placeOrder($waiter);

        $this->actingAs($waiter);

        $this->get(route('orders.receipt', $order))->assertNotFound();
    }

    public function test_a_waiter_cannot_print_another_waiters_receipt(): void
    {
        $waiter = $this->waiter('Garnet');
        $order = $this->paidOrder($waiter, $this->manager());

        $this->actingAs($this->waiter('Akua'));

        $this->get(route('orders.receipt', $order))->assertForbidden();
    }

    public function test_settling_a_tab_offers_the_receipt(): void
    {
        $waiter = $this->waiter();
        $manager = $this->manager();

        $orders = app(OrderService::class);
        $order = $this->placeOrder($waiter);
        $orders->markReady($order, $manager);

        $this->actingAs($manager);

        Livewire::test(Orders::class)
            ->call('openCloseModal', $order->id)
            ->call('confirmClose')
            ->assertHasNoErrors()
            ->assertSet('showReceiptModal', true)
            ->assertSee('Payment received')
            ->assertSee($order->code)
            ->assertSeeHtml('data-testid="open-receipt"');

        $this->get(route('orders.receipt', $order->fresh()))->assertOk();
    }
}
