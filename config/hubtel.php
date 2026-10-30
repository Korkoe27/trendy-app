<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile money payments (Hubtel)
    |--------------------------------------------------------------------------
    |
    | The whole integration is built but OFF by default. While this is false, the "Pay via Mobile
    | Money" option on the Close Tab dialog is greyed out, the order service refuses mobile-money
    | settlement, and the callback URL answers 404. Turn it on only after the endpoints below
    | have been tested against your Hubtel account.
    |
    */

    'payments_enabled' => (bool) env('HUBTEL_PAYMENTS_ENABLED', false),

    'pay' => [
        // Receive Money endpoint. "{merchant}" is replaced with HUBTEL_MERCHANT_ACCOUNT.
        'url' => env('HUBTEL_PAY_URL'),

        // Transaction Status endpoint, used to confirm a payment when no callback arrives.
        // "{merchant}" is replaced as above. Leave empty to rely on callbacks only.
        'status_url' => env('HUBTEL_PAY_STATUS_URL'),

        // API credentials (HTTP Basic). Keep these in .env only.
        'user' => env('HUBTEL_PAY_USER'),
        'key' => env('HUBTEL_PAY_KEY'),

        // Your Hubtel merchant / POS account number.
        'merchant_account' => env('HUBTEL_MERCHANT_ACCOUNT'),

        // Public https URL Hubtel calls with the result. If empty, the app's own webhook route is used.
        'callback_url' => env('HUBTEL_CALLBACK_URL'),

        // Secret path segment of the callback URL. Hubtel does not sign callbacks, so the URL itself is
        // the shared secret; callbacks are also confirmed against the amount and (when configured) the
        // status endpoint before an order is marked paid.
        'callback_token' => env('HUBTEL_CALLBACK_TOKEN'),

        'verify_with_status' => (bool) env('HUBTEL_VERIFY_WITH_STATUS', true),

        'timeout' => (int) env('HUBTEL_PAY_TIMEOUT', 20),

        // How long a customer has to approve the prompt before the attempt is treated as expired.
        'expire_minutes' => (int) env('HUBTEL_PAY_EXPIRE_MINUTES', 5),
    ],

];
