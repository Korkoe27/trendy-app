<?php

namespace App\Services\Payments;

use App\Jobs\CheckMobileMoneyPayment;
use App\Models\ActivityLogs;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\OrderService;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Settles an order with mobile money. The order is only marked paid here, and only when the
 * gateway has confirmed a payment whose amount matches the order total. The browser never
 * decides that a customer has paid.
 *
 * Every state change goes through applyResult(), which is idempotent: a repeated callback, a
 * status poll and a manual "check status" racing each other can only ever close the order once.
 *
 * The whole feature sits behind HUBTEL_PAYMENTS_ENABLED (config/hubtel.php).
 */
class MobileMoneyService
{
    public function __construct(
        private MobileMoneyGateway $gateway,
        private OrderService $orders,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('hubtel.payments_enabled', false);
    }

    /** Switched on in config AND the credentials/endpoints exist. */
    public function available(): bool
    {
        return $this->enabled() && $this->gateway->isConfigured();
    }

    public function activePayment(Order $order): ?OrderPayment
    {
        return $order->payments()->active()->latest('id')->first();
    }

    /**
     * Send a payment prompt for the order's current total to the customer's phone.
     *
     * @throws \DomainException
     */
    public function start(Order $order, User $staff, ?string $phone = null): OrderPayment
    {
        $this->orders->assertCanManage($order, $staff);
        $this->assertAvailable();

        $phone = Phone::normalize($phone ?: $order->customer_phone);

        if (! $phone) {
            throw new \DomainException('Enter a valid Ghana mobile money number.');
        }

        $channel = HubtelGateway::channelFor($phone);

        if (! $channel) {
            throw new \DomainException('That number does not look like an MTN, Telecel or AirtelTigo number.');
        }

        $payment = null;

        $this->orders->locked($order, function (Order $order) use (&$payment, $staff, $phone, $channel) {
            if (! $order->isOpen() || $order->isPaid()) {
                throw new \DomainException('This order is already '.($order->isPaid() ? 'paid' : $order->status).'.');
            }

            if ($order->status === Order::STATUS_PENDING) {
                throw new \DomainException('The bartender has not marked this order ready yet. Close the tab once it is ready.');
            }

            if ((float) $order->total <= 0) {
                throw new \DomainException('There is nothing to pay on this order.');
            }

            if ($order->payments()->active()->exists()) {
                throw new \DomainException('A mobile money payment is already waiting for the customer to approve.');
            }

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'client_reference' => $this->newReference($order),
                'phone' => $phone,
                'channel' => $channel,
                'amount' => $order->total, // always the server's total, never a number from the browser
                'status' => OrderPayment::STATUS_INITIATED,
                'initiated_by' => $staff->id,
                'initiated_by_name' => $staff->name ?? 'Unknown',
                'expires_at' => now()->addMinutes((int) config('hubtel.pay.expire_minutes', 5)),
            ]);

            $this->log($staff, 'order_payment_start', "Mobile money prompt for {$order->code}: ₵".number_format((float) $order->total, 2), $order, [
                'client_reference' => $payment->client_reference,
                'phone' => Phone::mask($phone),
            ]);

            return $order;
        });

        // The charge happens outside the database transaction so a slow gateway never holds row locks.
        try {
            $result = $this->gateway->charge($payment, $order->fresh());
        } catch (GatewayUnavailable $e) {
            // We do not know whether Hubtel processed the request. Never charge again blindly; keep the
            // attempt pending and let the status check or the callback settle it.
            Log::warning('Mobile money charge outcome unknown', ['payment' => $payment->id, 'error' => $e->getMessage()]);

            $payment->update(['status' => OrderPayment::STATUS_PENDING, 'response_payload' => ['unknown_outcome' => $e->getMessage()]]);

            return $payment->fresh();
        }

        $payment = $this->applyResult($payment, $result);

        // Safety net if the callback never arrives (a no-op on the sync queue, where the UI polls instead).
        if ($payment->isActive() && config('queue.default') !== 'sync') {
            CheckMobileMoneyPayment::dispatch($payment->id)->delay(now()->addSeconds(20));
        }

        return $payment;
    }

    /**
     * Fold a gateway result into the payment and, when it is a confirmed payment, into the order.
     * Safe to call any number of times with the same or stale information.
     */
    public function applyResult(OrderPayment $payment, GatewayResult $result, ?array $callbackPayload = null): OrderPayment
    {
        $this->orders->locked($payment->order, function (Order $order) use ($payment, $result, $callbackPayload) {
            $payment = OrderPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->isPaid()) {
                return $order; // already applied
            }

            $common = array_filter([
                'provider_reference' => $result->providerReference,
                'callback_payload' => $callbackPayload,
            ], fn ($v) => $v !== null);

            if ($result->isPending()) {
                if ($payment->status === OrderPayment::STATUS_INITIATED) {
                    $payment->update(['status' => OrderPayment::STATUS_PENDING, 'response_payload' => $result->raw] + $common);
                }

                return $order;
            }

            if ($result->isFailed()) {
                if ($payment->isActive()) {
                    $payment->update(['status' => OrderPayment::STATUS_FAILED, 'failure_reason' => Str::limit((string) $result->message, 250), 'response_payload' => $result->raw] + $common);
                    $this->log($this->actor($payment, $order), 'order_payment_failed', "Mobile money payment failed for {$order->code}", $order, ['reason' => $result->message]);
                }

                return $order;
            }

            // --- paid ---------------------------------------------------------------------------------
            $staff = $this->actor($payment, $order);

            if ($result->amount !== null && abs($result->amount - (float) $payment->amount) > 0.009) {
                // Money moved but not the amount we asked for: do not close the order, flag it for a human.
                $payment->update(['status' => OrderPayment::STATUS_FAILED, 'failure_reason' => 'AMOUNT MISMATCH: gateway reported '.$result->amount.', expected '.$payment->amount.'. Check the Hubtel dashboard.', 'response_payload' => $result->raw] + $common);
                $this->log($staff, 'order_payment_mismatch', "Mobile money amount mismatch on {$order->code}", $order, ['expected' => (float) $payment->amount, 'received' => $result->amount, 'client_reference' => $payment->client_reference]);

                return $order;
            }

            $payment->update(['status' => OrderPayment::STATUS_PAID, 'paid_at' => now(), 'failure_reason' => null, 'response_payload' => $result->raw] + $common);

            if ($order->isPaid() || ! $order->isOpen()) {
                // Paid some other way (or cancelled) while the customer was approving: the money must be refunded.
                $this->log($staff, 'order_payment_duplicate', "Mobile money received for {$order->code} but the order was already {$order->status}. Refund needed.", $order, ['client_reference' => $payment->client_reference, 'amount' => (float) $payment->amount]);

                return $order;
            }

            if (abs((float) $order->total - (float) $payment->amount) > 0.009 || $order->status === Order::STATUS_PENDING) {
                $this->log($staff, 'order_payment_unapplied', "Mobile money received for {$order->code} but the order changed. Settle it manually.", $order, ['client_reference' => $payment->client_reference, 'amount' => (float) $payment->amount, 'order_total' => (float) $order->total]);

                return $order;
            }

            $this->orders->settle($order, $staff, 'momo');

            return $order;
        });

        return $payment->fresh();
    }

    /** Ask the gateway where an attempt stands and apply the answer. Used by the poller and "Check status". */
    public function refresh(OrderPayment $payment): OrderPayment
    {
        $payment = $payment->fresh();

        if (! $payment->isActive()) {
            return $payment;
        }

        try {
            $result = $this->gateway->status($payment);
        } catch (GatewayUnavailable $e) {
            Log::info('Mobile money status check unavailable', ['payment' => $payment->id, 'error' => $e->getMessage()]);

            return $payment;
        }

        if ($result->isPending() && $payment->isStale()) {
            return $this->expire($payment);
        }

        return $this->applyResult($payment, $result);
    }

    /** The waiter gives up waiting. A late approval is still honoured if the order is untouched. */
    public function cancelAttempt(Order $order, User $staff): ?OrderPayment
    {
        $cancelled = null;

        $this->orders->assertCanManage($order, $staff);

        $this->orders->locked($order, function (Order $order) use ($staff, &$cancelled) {
            $payment = $order->payments()->active()->lockForUpdate()->latest('id')->first();

            if (! $payment) {
                return $order;
            }

            $payment->update(['status' => OrderPayment::STATUS_CANCELLED, 'failure_reason' => 'Cancelled by '.($staff->name ?? 'staff')]);
            $this->log($staff, 'order_payment_cancel', "Mobile money attempt cancelled for {$order->code}", $order, ['client_reference' => $payment->client_reference]);

            $cancelled = $payment;

            return $order;
        });

        return $cancelled?->fresh();
    }

    /**
     * Handle Hubtel's callback. Returns false when the callback is not about a payment we know.
     * Callbacks are not signed, so a "paid" callback is confirmed with the status endpoint when
     * one is configured, and its amount must match; otherwise it is left for the poller.
     */
    public function handleCallback(array $payload): bool
    {
        $result = $this->gateway->parseCallback($payload);

        $payment = $result->clientReference
            ? OrderPayment::where('client_reference', $result->clientReference)->first()
            : null;

        if (! $payment) {
            Log::warning('Mobile money callback for an unknown payment', ['reference' => $result->clientReference]);

            return false;
        }

        if ($result->isPaid() && config('hubtel.pay.verify_with_status', true) && $this->gateway->canVerify()) {
            try {
                $verified = $this->gateway->status($payment);
            } catch (GatewayUnavailable) {
                return true; // cannot confirm right now; the poller will
            }

            if (! $verified->isPaid()) {
                Log::warning('Mobile money callback said paid but the status check disagrees', ['payment' => $payment->id]);

                return true;
            }
        }

        $this->applyResult($payment, $result, $result->raw);

        return true;
    }

    // ------------------------------------------------------------------ internals

    private function expire(OrderPayment $payment): OrderPayment
    {
        $this->orders->locked($payment->order, function (Order $order) use ($payment) {
            $fresh = OrderPayment::lockForUpdate()->find($payment->id);

            if ($fresh && $fresh->isActive()) {
                $fresh->update(['status' => OrderPayment::STATUS_EXPIRED, 'failure_reason' => 'The customer did not approve in time.']);
                $this->log($this->actor($fresh, $order), 'order_payment_expired', "Mobile money prompt expired for {$order->code}", $order, ['client_reference' => $fresh->client_reference]);
            }

            return $order;
        });

        return $payment->fresh();
    }

    private function assertAvailable(): void
    {
        if (! $this->enabled()) {
            throw new \DomainException('Mobile money payments are not enabled yet. Please settle with cash.');
        }

        if (! $this->gateway->isConfigured()) {
            throw new \DomainException('Mobile money is switched on but not fully configured. Please settle with cash.');
        }
    }

    private function newReference(Order $order): string
    {
        return 'TRD'.$order->id.'-'.Str::upper(Str::random(8));
    }

    /** The staff member the system acts for when a callback arrives with nobody logged in. */
    private function actor(OrderPayment $payment, Order $order): User
    {
        return User::find($payment->initiated_by)
            ?? User::find($order->taken_by)
            ?? User::query()->orderBy('id')->firstOrFail();
    }

    private function log(User $staff, string $action, string $description, Order $order, array $extra = []): void
    {
        ActivityLogs::create([
            'user_id' => $staff->id,
            'action_type' => $action,
            'description' => $description,
            'entity_type' => 'order',
            'entity_id' => $order->id,
            'metadata' => array_merge(['code' => $order->code, 'timestamp' => now()], $extra),
        ]);
    }
}
