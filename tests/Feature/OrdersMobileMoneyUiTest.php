<?php

namespace Tests\Feature;

use App\Livewire\Pages\Orders;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\MobileMoneyGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\FakeMobileMoneyGateway;
use Tests\TestCase;

class OrdersMobileMoneyUiTest extends TestCase
{
    use RefreshDatabase;

    private FakeMobileMoneyGateway $gateway;

    private User $waiter;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeMobileMoneyGateway;
        $this->app->instance(MobileMoneyGateway::class, $this->gateway);

        $category = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $beer = DB::table('products')->insertGetId(['name' => 'Club Beer', 'category_id' => $category, 'selling_price' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        // A cashier: the seeded Waiter role plus the manager right to close with cash (order_debts -> modify).
        $cashierRole = DB::table('roles')->insertGetId(['name' => 'Cashier', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['orders' => [true, true, false], 'order_debts' => [true, false, true]] as $module => [$view, $create, $modify]) {
            DB::table('permissions')->insert([
                'role_id' => $cashierRole, 'module_id' => DB::table('modules')->where('name', $module)->value('id'),
                'can_view' => $view, 'can_create' => $create, 'can_modify' => $modify, 'can_delete' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->waiter = User::forceCreate([
            'name' => 'Garnet', 'username' => 'garnet', 'phone' => '0241000001',
            'role_id' => $cashierRole,
            'password' => '482913', 'must_change_password' => false,
        ]);
        $bartender = User::forceCreate([
            'name' => 'Akua', 'username' => 'akua', 'phone' => '0241000002',
            'role_id' => DB::table('roles')->where('name', 'Bartender')->value('id'),
            'password' => '482913', 'must_change_password' => false,
        ]);

        $service = app(OrderService::class);
        $this->order = $service->markReady($service->place($this->waiter, 'Kwame Mensah', '0241234567', [$beer => 2]), $bartender);

        $this->actingAs($this->waiter);
    }

    public function test_the_option_is_greyed_out_and_unusable_while_the_flag_is_off(): void
    {
        config(['hubtel.payments_enabled' => false]);

        Livewire::test(Orders::class)
            ->call('openCloseModal', $this->order->id)
            ->assertSee('Coming soon')
            ->assertDontSee('Number to charge')
            ->assertDontSee('SEND PAYMENT PROMPT')
            ->call('startMobileMoney')
            ->assertHasErrors(['paymentMethod']);

        $this->assertSame(0, OrderPayment::count());
        $this->assertSame([], $this->gateway->charges);
    }

    public function test_switched_on_but_unconfigured_says_not_set_up(): void
    {
        config(['hubtel.payments_enabled' => true]);
        $this->gateway->configured = false;

        Livewire::test(Orders::class)
            ->call('openCloseModal', $this->order->id)
            ->assertSee('Not set up')
            ->assertDontSee('Coming soon');
    }

    public function test_when_enabled_the_waiter_sends_a_prompt_and_the_modal_waits(): void
    {
        config(['hubtel.payments_enabled' => true]);

        Livewire::test(Orders::class)
            ->call('openCloseModal', $this->order->id)
            ->assertDontSee('Coming soon')
            ->assertSet('momoPhone', '0241234567')
            ->set('paymentMethod', 'momo')
            ->assertSee('Number to charge')
            ->assertSee('SEND PAYMENT PROMPT')
            ->set('momoPhone', '055 111 2222')
            ->call('startMobileMoney')
            ->assertHasNoErrors()
            ->assertSee('Waiting for the customer to approve')
            ->assertSee('0551112222')
            ->assertDontSee('COLLECT CASH');

        $payment = OrderPayment::firstOrFail();
        $this->assertSame('+233551112222', $payment->phone);
        $this->assertSame('60.00', $payment->amount);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
    }

    public function test_the_board_marks_orders_waiting_for_approval(): void
    {
        config(['hubtel.payments_enabled' => true]);

        Livewire::test(Orders::class)->call('openCloseModal', $this->order->id)->set('paymentMethod', 'momo')->call('startMobileMoney');

        Livewire::test(Orders::class)->assertSee('Awaiting mobile money approval');
    }

    public function test_the_modal_closes_by_itself_once_the_customer_has_paid(): void
    {
        config(['hubtel.payments_enabled' => true]);

        $component = Livewire::test(Orders::class)
            ->call('openCloseModal', $this->order->id)
            ->set('paymentMethod', 'momo')
            ->call('startMobileMoney');

        $component->call('checkPayment')->assertSet('showCloseModal', true); // still pending

        $payment = OrderPayment::firstOrFail();
        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);

        $component->call('checkPayment')
            ->assertSet('showCloseModal', false)
            ->assertSee('paid by mobile money');

        $this->assertSame('closed', $this->order->fresh()->status);
        $this->assertSame('momo', $this->order->fresh()->payment_method);
    }

    public function test_a_failed_prompt_shows_why_and_offers_cash(): void
    {
        config(['hubtel.payments_enabled' => true]);
        $this->gateway->chargeOutcome = FakeMobileMoneyGateway::failed(new OrderPayment(['client_reference' => 'x']), 'Insufficient funds');

        Livewire::test(Orders::class)
            ->call('openCloseModal', $this->order->id)
            ->set('paymentMethod', 'momo')
            ->call('startMobileMoney')
            ->assertSee('did not go through')
            ->assertSee('Insufficient funds')
            ->assertSee('Cash settlement')
            ->set('paymentMethod', 'cash')
            ->call('confirmClose')
            ->assertSet('showCloseModal', false);

        $this->assertSame('cash', $this->order->fresh()->payment_method);
    }

    public function test_cancelling_the_attempt_returns_to_the_cash_option(): void
    {
        config(['hubtel.payments_enabled' => true]);

        Livewire::test(Orders::class)
            ->call('openCloseModal', $this->order->id)
            ->set('paymentMethod', 'momo')
            ->call('startMobileMoney')
            ->assertSee('Waiting for the customer')
            ->call('cancelPayment')
            ->assertDontSee('Waiting for the customer')
            ->assertSet('paymentMethod', 'cash')
            ->assertSee('COLLECT CASH');

        $this->assertSame('cancelled', OrderPayment::firstOrFail()->status);
    }

    public function test_reopening_the_modal_during_a_pending_payment_shows_the_waiting_state(): void
    {
        config(['hubtel.payments_enabled' => true]);

        Livewire::test(Orders::class)->call('openCloseModal', $this->order->id)->set('paymentMethod', 'momo')->call('startMobileMoney');

        Livewire::test(Orders::class)
            ->call('openCloseModal', $this->order->id)
            ->assertSet('paymentMethod', 'momo')
            ->assertSee('Waiting for the customer to approve');
    }

    public function test_cash_cannot_be_used_while_a_prompt_is_out(): void
    {
        config(['hubtel.payments_enabled' => true]);

        $component = Livewire::test(Orders::class)->call('openCloseModal', $this->order->id)->set('paymentMethod', 'momo')->call('startMobileMoney');

        $component->set('paymentMethod', 'cash')->call('confirmClose')->assertHasErrors(['paymentMethod']);

        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
    }
}
