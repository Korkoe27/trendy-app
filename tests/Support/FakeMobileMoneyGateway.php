<?php

namespace Tests\Support;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\GatewayUnavailable;
use App\Services\Payments\HubtelGateway;
use App\Services\Payments\MobileMoneyGateway;

/** A scriptable gateway for tests: set what charge()/status() should answer, then inspect the calls. */
class FakeMobileMoneyGateway implements MobileMoneyGateway
{
    public bool $configured = true;

    public bool $verifiable = true;

    /** @var GatewayResult|\Throwable|null null = "pending, waiting for the customer" */
    public $chargeOutcome = null;

    /** @var GatewayResult|\Throwable|null */
    public $statusOutcome = null;

    /** @var list<array{reference: string, phone: string, channel: ?string, amount: float}> */
    public array $charges = [];

    public int $statusChecks = 0;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function canVerify(): bool
    {
        return $this->verifiable;
    }

    public function charge(OrderPayment $payment, Order $order): GatewayResult
    {
        $this->charges[] = [
            'reference' => $payment->client_reference,
            'phone' => $payment->phone,
            'channel' => $payment->channel,
            'amount' => (float) $payment->amount,
        ];

        return $this->resolve($this->chargeOutcome, $payment);
    }

    public function status(OrderPayment $payment): GatewayResult
    {
        $this->statusChecks++;

        return $this->resolve($this->statusOutcome, $payment);
    }

    public function parseCallback(array $payload): GatewayResult
    {
        return (new HubtelGateway)->parseCallback($payload);
    }

    public static function paid(OrderPayment $payment, ?float $amount = null): GatewayResult
    {
        return new GatewayResult(GatewayResult::PAID, $payment->client_reference, 'HUB-'.$payment->id, $amount ?? (float) $payment->amount);
    }

    public static function failed(OrderPayment $payment, string $why = 'Declined'): GatewayResult
    {
        return new GatewayResult(GatewayResult::FAILED, $payment->client_reference, null, null, $why);
    }

    /** A Hubtel-shaped callback body for a payment. */
    public static function callbackBody(OrderPayment $payment, string $code = '0000', ?float $amount = null): array
    {
        return [
            'ResponseCode' => $code,
            'Message' => $code === '0000' ? 'success' : 'failed',
            'Data' => [
                'ClientReference' => $payment->client_reference,
                'TransactionId' => 'HUB-'.$payment->id,
                'Amount' => $amount ?? (float) $payment->amount,
                'Description' => 'The MTN Mobile Money payment has been approved and processed successfully.',
            ],
        ];
    }

    private function resolve($outcome, OrderPayment $payment): GatewayResult
    {
        if ($outcome instanceof \Throwable) {
            throw $outcome;
        }

        return $outcome ?? new GatewayResult(GatewayResult::PENDING, $payment->client_reference, 'HUB-'.$payment->id, (float) $payment->amount);
    }

    public static function unavailable(): GatewayUnavailable
    {
        return new GatewayUnavailable('Connection timed out');
    }
}
