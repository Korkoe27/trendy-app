<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderPayment;

/**
 * A mobile-money provider. Everything provider-specific (URLs, payload fields, response codes)
 * lives behind this interface, so adapting to the real API means editing one class.
 */
interface MobileMoneyGateway
{
    /** Are the credentials and endpoints needed to charge present? */
    public function isConfigured(): bool;

    /** Can a payment's state be confirmed with the provider (a status endpoint exists)? */
    public function canVerify(): bool;

    /**
     * Ask the provider to send a payment prompt to the customer's phone.
     *
     * @throws GatewayUnavailable when the outcome is unknown
     */
    public function charge(OrderPayment $payment, Order $order): GatewayResult;

    /**
     * Look up the current state of an attempt.
     *
     * @throws GatewayUnavailable
     */
    public function status(OrderPayment $payment): GatewayResult;

    /** Turn the provider's callback body into a result. The caller still has to verify it. */
    public function parseCallback(array $payload): GatewayResult;
}
