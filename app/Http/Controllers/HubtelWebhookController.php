<?php

namespace App\Http\Controllers;

use App\Services\Payments\MobileMoneyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Hubtel's mobile-money result. Reachable only when HUBTEL_PAYMENTS_ENABLED is on and the
 * secret token in the URL matches; otherwise it answers 404 as if it did not exist.
 */
class HubtelWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, MobileMoneyService $payments)
    {
        $expected = (string) config('hubtel.pay.callback_token');

        abort_unless($payments->enabled() && $expected !== '' && hash_equals($expected, $token), 404);

        try {
            $known = $payments->handleCallback($request->all());
        } catch (\Throwable $e) {
            // A 5xx makes Hubtel retry the callback, which is what we want if something broke on our side.
            Log::error('Hubtel callback failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => $known]);
    }
}
