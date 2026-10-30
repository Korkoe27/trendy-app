<?php

namespace App\Jobs;

use App\Models\OrderPayment;
use App\Services\Payments\MobileMoneyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Safety net for a callback that never arrives: asks the gateway about a pending payment every
 * 20 seconds until it is paid, failed or expired. Does nothing once the payment is settled.
 */
class CheckMobileMoneyPayment implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(public int $paymentId, public int $round = 1)
    {
    }

    public function handle(MobileMoneyService $service): void
    {
        $payment = OrderPayment::find($this->paymentId);

        if (! $payment || ! $payment->isActive()) {
            return;
        }

        $payment = $service->refresh($payment);

        // Keep checking a little past the expiry, in case the customer approves at the last moment.
        $maxRounds = (int) ceil(((int) config('hubtel.pay.expire_minutes', 5) + 2) * 60 / 20);

        if ($payment->isActive() && $this->round < $maxRounds && config('queue.default') !== 'sync') {
            self::dispatch($this->paymentId, $this->round + 1)->delay(now()->addSeconds(20));
        }
    }
}
