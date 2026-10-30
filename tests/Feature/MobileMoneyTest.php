<?php

namespace Tests\Feature;

use App\Jobs\CheckMobileMoneyPayment;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\MobileMoneyGateway;
use App\Services\Payments\MobileMoneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeMobileMoneyGateway;
use Tests\TestCase;

class MobileMoneyTest extends TestCase
{
    use RefreshDatabase;

    private FakeMobileMoneyGateway $gateway;

    private MobileMoneyService $momo;

    private OrderService $orders;

    private User $waiter;

    private User $bartender;

    private int $beer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hubtel.payments_enabled' => true, 'hubtel.pay.verify_with_status' => true, 'hubtel.pay.expire_minutes' => 5]);

        $this->gateway = new FakeMobileMoneyGateway;
        $this->app->instance(MobileMoneyGateway::class, $this->gateway);

        $this->momo = app(MobileMoneyService::class);
        $this->orders = app(OrderService::class);

        $roleId = DB::table('roles')->insertGetId(['name' => 'Staff', 'created_at' => now(), 'updated_at' => now()]);

        // Waiters hold orders -> create; only the waiter who took an order (or a manager) may add to or close it.
        DB::table('permissions')->insert([
            'role_id' => $roleId, 'module_id' => DB::table('modules')->where('name', 'orders')->value('id'),
            'can_view' => true, 'can_create' => true, 'can_modify' => false, 'can_delete' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Closing with cash is a manager/admin right (order_debts -> modify); these tests act as a cashier.
        DB::table('permissions')->insert([
            'role_id' => $roleId, 'module_id' => DB::table('modules')->where('name', 'order_debts')->value('id'),
            'can_view' => true, 'can_create' => false, 'can_modify' => true, 'can_delete' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $category = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $this->beer = DB::table('products')->insertGetId(['name' => 'Club Beer', 'category_id' => $category, 'selling_price' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->waiter = User::forceCreate(['name' => 'Garnet', 'username' => 'garnet', 'phone' => '0241111111', 'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false]);
        $this->bartender = User::forceCreate(['name' => 'Akua', 'username' => 'akua', 'phone' => '0242222222', 'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false]);
    }

    private function readyOrder(int $beers = 2, string $phone = '0241234567'): Order
    {
        $order = $this->orders->place($this->waiter, 'Kwame Mensah', $phone, [$this->beer => $beers]);

        return $this->orders->markReady($order, $this->bartender);
    }

    private function logged(string $action): int
    {
        return DB::table('activity_logs')->where('action_type', $action)->count();
    }

    // ---------------------------------------------------------------- off switch

    public function test_nothing_happens_while_the_feature_is_off(): void
    {
        config(['hubtel.payments_enabled' => false]);
        $order = $this->readyOrder();

        try {
            $this->momo->start($order, $this->waiter);
            $this->fail('Mobile money must be refused while switched off');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('not enabled', $e->getMessage());
        }

        $this->assertSame(0, OrderPayment::count());
        $this->assertSame([], $this->gateway->charges);
    }

    public function test_it_refuses_to_run_when_switched_on_but_not_configured(): void
    {
        $this->gateway->configured = false;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not fully configured');
        $this->momo->start($this->readyOrder(), $this->waiter);
    }

    public function test_the_webhook_is_a_404_while_off_or_with_the_wrong_token(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        config(['hubtel.pay.callback_token' => 'secret-token']);

        config(['hubtel.payments_enabled' => false]);
        $this->postJson('webhooks/hubtel/payments/secret-token', FakeMobileMoneyGateway::callbackBody($payment))->assertNotFound();

        config(['hubtel.payments_enabled' => true]);
        $this->postJson('webhooks/hubtel/payments/wrong-token', FakeMobileMoneyGateway::callbackBody($payment))->assertNotFound();

        config(['hubtel.pay.callback_token' => '']);
        $this->postJson('webhooks/hubtel/payments/', FakeMobileMoneyGateway::callbackBody($payment))->assertNotFound();

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    // ---------------------------------------------------------------- starting a payment

    public function test_starting_sends_a_prompt_for_the_server_side_total(): void
    {
        $order = $this->readyOrder(3); // 90

        $payment = $this->momo->start($order, $this->waiter);

        $this->assertSame('pending', $payment->status);
        $this->assertSame('90.00', $payment->amount);
        $this->assertSame('+233241234567', $payment->phone);
        $this->assertSame('mtn-gh', $payment->channel);
        $this->assertSame($this->waiter->id, $payment->initiated_by);
        $this->assertNotNull($payment->expires_at);

        $this->assertCount(1, $this->gateway->charges);
        $this->assertSame(['reference' => $payment->client_reference, 'phone' => '+233241234567', 'channel' => 'mtn-gh', 'amount' => 90.0], $this->gateway->charges[0]);

        $order->refresh();
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('ready', $order->status);
        $this->assertSame(1, $this->logged('order_payment_start'));
    }

    public function test_a_different_number_can_be_charged(): void
    {
        $payment = $this->momo->start($this->readyOrder(), $this->waiter, '020 555 0000');

        $this->assertSame('+233205550000', $payment->phone);
        $this->assertSame('vodafone-gh', $payment->channel);
    }

    public function test_starting_is_refused_for_bad_numbers_pending_paid_and_double_attempts(): void
    {
        $order = $this->readyOrder();

        foreach (['12345', '0301234567'] as $bad) {
            try {
                $this->momo->start($order, $this->waiter, $bad);
                $this->fail("Expected {$bad} to be refused");
            } catch (\DomainException) {
            }
        }

        $pending = $this->orders->place($this->waiter, 'Not Ready', '0241234567', [$this->beer => 1]);
        try {
            $this->momo->start($pending, $this->waiter);
            $this->fail('A pending order cannot be paid');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('not marked this order ready', $e->getMessage());
        }

        $this->momo->start($order, $this->waiter);
        try {
            $this->momo->start($order, $this->waiter);
            $this->fail('Only one prompt at a time');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('already waiting', $e->getMessage());
        }

        $this->assertCount(1, $this->gateway->charges);
    }

    public function test_an_immediate_success_closes_the_order(): void
    {
        $order = $this->readyOrder();
        $this->gateway->chargeOutcome = new GatewayResult(GatewayResult::PAID, null, 'HUB-1', 60.0);

        $payment = $this->momo->start($order, $this->waiter);

        $this->assertSame('paid', $payment->status);
        $order->refresh();
        $this->assertSame('closed', $order->status);
        $this->assertSame('momo', $order->payment_method);
    }

    public function test_a_declined_prompt_leaves_the_order_open_and_can_be_retried(): void
    {
        $order = $this->readyOrder();
        $this->gateway->chargeOutcome = new GatewayResult(GatewayResult::FAILED, null, null, null, 'Insufficient funds');

        $payment = $this->momo->start($order, $this->waiter);

        $this->assertSame('failed', $payment->status);
        $this->assertSame('Insufficient funds', $payment->failure_reason);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->gateway->chargeOutcome = null;
        $this->assertSame('pending', $this->momo->start($order, $this->waiter)->status);
    }

    public function test_an_unreachable_gateway_leaves_the_attempt_pending_and_never_charges_twice(): void
    {
        $order = $this->readyOrder();
        $this->gateway->chargeOutcome = FakeMobileMoneyGateway::unavailable();

        $payment = $this->momo->start($order, $this->waiter);

        $this->assertSame('pending', $payment->status);
        $this->assertArrayHasKey('unknown_outcome', $payment->response_payload);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Trying again is blocked: we must find out what happened to the first one.
        $this->expectException(\DomainException::class);
        $this->momo->start($order, $this->waiter);
    }

    // ---------------------------------------------------------------- confirmation

    public function test_a_paid_callback_closes_the_order_once(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);

        $this->assertTrue($this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment)));
        $this->assertTrue($this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment))); // Hubtel retries

        $order->refresh();
        $this->assertSame('closed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('momo', $order->payment_method);
        $this->assertSame($this->waiter->id, $order->closed_by);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('HUB-'.$payment->id, $payment->provider_reference);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1, $this->logged('order_paid'));
    }

    public function test_the_webhook_route_applies_a_valid_callback(): void
    {
        config(['hubtel.pay.callback_token' => 'secret-token']);
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);

        $this->postJson('webhooks/hubtel/payments/secret-token', FakeMobileMoneyGateway::callbackBody($payment))
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('closed', $order->fresh()->status);
    }

    public function test_a_callback_for_an_unknown_reference_is_ignored(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $body = FakeMobileMoneyGateway::callbackBody($payment);
        $body['Data']['ClientReference'] = 'TRD999-FORGED00';

        $this->assertFalse($this->momo->handleCallback($body));
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_a_paid_callback_is_not_believed_when_the_status_check_disagrees(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $this->gateway->statusOutcome = null; // still pending according to Hubtel

        $this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment));

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_a_paid_callback_waits_for_the_poller_when_the_status_check_is_unreachable(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $this->gateway->statusOutcome = FakeMobileMoneyGateway::unavailable();

        $this->assertTrue($this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment)));
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);
        $this->momo->refresh($payment);
        $this->assertSame('closed', $order->fresh()->status);
    }

    public function test_without_a_status_endpoint_the_callback_is_trusted_after_amount_and_reference_match(): void
    {
        $this->gateway->verifiable = false;
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);

        $this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment));

        $this->assertSame('closed', $order->fresh()->status);
        $this->assertSame(0, $this->gateway->statusChecks);
    }

    public function test_a_failed_callback_marks_the_attempt_failed_and_keeps_the_tab_open(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);

        $this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment, '2001'));

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_a_wrong_amount_never_closes_the_order_and_is_flagged(): void
    {
        $order = $this->readyOrder(); // 60
        $payment = $this->momo->start($order, $this->waiter);
        $this->gateway->verifiable = false;

        $this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment, '0000', 10.0));

        $payment->refresh();
        $this->assertSame('failed', $payment->status);
        $this->assertStringContainsString('AMOUNT MISMATCH', $payment->failure_reason);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(1, $this->logged('order_payment_mismatch'));
    }

    // ---------------------------------------------------------------- races and edits

    public function test_a_repeat_order_is_refused_rather_than_split_while_a_prompt_is_out(): void
    {
        $order = $this->readyOrder();
        $this->momo->start($order, $this->waiter);

        try {
            $this->orders->placeOrAddToTab($this->waiter, 'Kwame Mensah', '0241234567', [$this->beer => 1]);
            $this->fail('The bill must not change or be split while a payment is waiting');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('mobile money payment is waiting', $e->getMessage());
        }

        $this->assertSame(1, Order::count());
        $this->assertSame('60.00', $order->fresh()->total);
    }

    public function test_the_order_cannot_be_changed_or_settled_another_way_while_a_prompt_is_out(): void
    {
        $order = $this->readyOrder();
        $this->momo->start($order, $this->waiter);

        foreach ([
            fn () => $this->orders->addItems($order, $this->waiter, [$this->beer => 1]),
            fn () => $this->orders->closeWithCash($order, $this->waiter),
            fn () => $this->orders->cancel($order, $this->waiter, 'Changed mind'),
            fn () => $this->orders->setItemQuantity($order, $this->waiter, $order->items->first()->id, 5),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the order to be locked by the pending payment');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('mobile money payment is waiting', $e->getMessage());
            }
        }

        $this->assertSame('60.00', $order->fresh()->total);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_cancelling_the_attempt_frees_the_order_for_cash(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);

        $cancelled = $this->momo->cancelAttempt($order, $this->waiter);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('closed', $this->orders->closeWithCash($order, $this->waiter)->status);
        $this->assertSame(1, $this->logged('order_payment_cancel'));
    }

    public function test_money_arriving_after_the_tab_was_paid_in_cash_is_flagged_for_refund(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $this->momo->cancelAttempt($order, $this->waiter);
        $this->orders->closeWithCash($order, $this->waiter);

        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);
        $this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment));

        $this->assertSame('cash', $order->fresh()->payment_method, 'The order must keep its cash settlement');
        $this->assertSame('paid', $payment->fresh()->status, 'The money really arrived, so it is recorded');
        $this->assertSame(1, $this->logged('order_payment_duplicate'));
        $this->assertSame(1, $this->logged('order_paid'));
    }

    public function test_a_late_payment_for_a_changed_order_is_flagged_not_applied(): void
    {
        $order = $this->readyOrder(); // 60
        $payment = $this->momo->start($order, $this->waiter);
        $this->momo->cancelAttempt($order, $this->waiter);
        $this->orders->addItems($order, $this->waiter, [$this->beer => 1]); // now 90, back to pending

        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);
        $this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment));

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(1, $this->logged('order_payment_unapplied'));
    }

    public function test_a_late_approval_after_cancelling_still_settles_an_untouched_order(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $this->momo->cancelAttempt($order, $this->waiter);

        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);
        $this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment));

        $this->assertSame('closed', $order->fresh()->status);
        $this->assertSame('momo', $order->fresh()->payment_method);
    }

    // ---------------------------------------------------------------- polling and expiry

    public function test_refresh_closes_the_order_when_the_gateway_says_paid(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);

        $this->assertSame('paid', $this->momo->refresh($payment)->status);
        $this->assertSame('closed', $order->fresh()->status);
    }

    public function test_refresh_keeps_waiting_until_the_prompt_expires(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);

        $this->assertSame('pending', $this->momo->refresh($payment)->status);

        $payment->update(['expires_at' => now()->subMinute()]);
        $expired = $this->momo->refresh($payment);

        $this->assertSame('expired', $expired->status);
        $this->assertSame(1, $this->logged('order_payment_expired'));
        $this->assertSame('closed', $this->orders->closeWithCash($order, $this->waiter)->status, 'An expired prompt frees the tab');
    }

    public function test_a_payment_approved_after_expiry_is_still_honoured(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $payment->update(['expires_at' => now()->subMinute()]);
        $this->momo->refresh($payment);
        $this->assertSame('expired', $payment->fresh()->status);

        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);
        $this->momo->handleCallback(FakeMobileMoneyGateway::callbackBody($payment));

        $this->assertSame('closed', $order->fresh()->status);
    }

    public function test_refresh_survives_an_unreachable_status_endpoint(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $this->gateway->statusOutcome = FakeMobileMoneyGateway::unavailable();

        $this->assertSame('pending', $this->momo->refresh($payment)->status);
    }

    public function test_the_poller_job_settles_a_pending_payment_and_ignores_settled_ones(): void
    {
        $order = $this->readyOrder();
        $payment = $this->momo->start($order, $this->waiter);
        $this->gateway->statusOutcome = FakeMobileMoneyGateway::paid($payment);

        (new CheckMobileMoneyPayment($payment->id))->handle($this->momo);
        $this->assertSame('closed', $order->fresh()->status);

        $checks = $this->gateway->statusChecks;
        (new CheckMobileMoneyPayment($payment->id))->handle($this->momo);
        $this->assertSame($checks, $this->gateway->statusChecks, 'A settled payment is not polled again');
    }
}
