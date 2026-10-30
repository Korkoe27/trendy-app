<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Hubtel "Receive Money" (mobile money prompt) and "Transaction Status".
 *
 * ASSUMPTIONS TO CONFIRM against your Hubtel account while testing (all of them are isolated here):
 *  - Charge: POST {HUBTEL_PAY_URL} with HTTP Basic auth and a JSON body of CustomerName,
 *    CustomerMsisdn (233XXXXXXXXX), Channel (mtn-gh | vodafone-gh | tigo-gh), Amount,
 *    PrimaryCallbackUrl, Description, ClientReference.
 *  - Charge response: ResponseCode "0001" = prompt sent, waiting for the customer; "0000" = already
 *    paid; anything else = declined. Data.TransactionId is Hubtel's reference.
 *  - Callback: the same shape, with ResponseCode "0000" for success and Data.ClientReference,
 *    Data.Amount, Data.TransactionId.
 *  - Status: GET {HUBTEL_PAY_STATUS_URL}?clientReference=... answering data.status of
 *    "Paid" | "Unpaid" | "Refunded" (this endpoint usually needs your server IP whitelisted by Hubtel).
 */
class HubtelGateway implements MobileMoneyGateway
{
    private const CHANNELS = [
        'mtn' => 'mtn-gh',
        'telecel' => 'vodafone-gh',
        'airteltigo' => 'tigo-gh',
    ];

    public function isConfigured(): bool
    {
        return ! empty(config('hubtel.pay.url'))
            && ! empty(config('hubtel.pay.user'))
            && ! empty(config('hubtel.pay.key'))
            && ! empty(config('hubtel.pay.merchant_account'));
    }

    public function canVerify(): bool
    {
        return $this->isConfigured() && ! empty(config('hubtel.pay.status_url'));
    }

    /** Hubtel's channel name for a phone number, or null when the network cannot be told from the prefix. */
    public static function channelFor(?string $phone): ?string
    {
        $network = Phone::network($phone);

        return $network ? self::CHANNELS[$network] : null;
    }

    public function charge(OrderPayment $payment, Order $order): GatewayResult
    {
        if (! $this->isConfigured()) {
            throw new \DomainException('Hubtel payments are not configured.');
        }

        $payload = [
            'CustomerName' => $order->customer_name,
            'CustomerMsisdn' => Phone::international($payment->phone),
            'Channel' => $payment->channel,
            'Amount' => (float) $payment->amount,
            'PrimaryCallbackUrl' => $this->callbackUrl(),
            'Description' => 'Trendy tab '.$order->code,
            'ClientReference' => $payment->client_reference,
        ];

        $response = $this->send(fn (PendingRequest $http) => $http->post($this->url('url'), $payload));

        return $this->fromChargeResponse($response, $payment);
    }

    public function status(OrderPayment $payment): GatewayResult
    {
        if (! $this->canVerify()) {
            return new GatewayResult(GatewayResult::PENDING, $payment->client_reference, message: 'Status checks are not configured.');
        }

        $response = $this->send(fn (PendingRequest $http) => $http->get($this->url('status_url'), ['clientReference' => $payment->client_reference]));

        $data = $response->json('data') ?? [];
        $status = Str::lower((string) ($data['status'] ?? ''));

        $state = match (true) {
            $status === 'paid' => GatewayResult::PAID,
            $status === 'refunded' => GatewayResult::FAILED,
            default => GatewayResult::PENDING, // "unpaid" can still turn into "paid"; expiry is decided by the caller
        };

        return new GatewayResult(
            $state,
            $payment->client_reference,
            $this->nullableString($data['transactionId'] ?? null),
            isset($data['amount']) ? (float) $data['amount'] : null,
            $response->json('message'),
            $this->scrub($response->json() ?? []),
        );
    }

    public function parseCallback(array $payload): GatewayResult
    {
        $data = $payload['Data'] ?? $payload['data'] ?? [];
        $code = (string) ($payload['ResponseCode'] ?? $payload['responseCode'] ?? '');

        return new GatewayResult(
            $this->stateFromCode($code),
            $this->nullableString($data['ClientReference'] ?? $data['clientReference'] ?? null),
            $this->nullableString($data['TransactionId'] ?? $data['transactionId'] ?? $data['ExternalTransactionId'] ?? null),
            isset($data['Amount']) ? (float) $data['Amount'] : (isset($data['amount']) ? (float) $data['amount'] : null),
            $payload['Message'] ?? $payload['message'] ?? ($data['Description'] ?? null),
            $this->scrub($payload),
        );
    }

    // ------------------------------------------------------------------ internals

    private function fromChargeResponse(Response $response, OrderPayment $payment): GatewayResult
    {
        $json = $response->json() ?? [];
        $data = $json['Data'] ?? [];
        $state = $this->stateFromCode((string) ($json['ResponseCode'] ?? ''));

        // A 4xx with no recognisable code is a refusal, never a pending payment.
        if (! $response->successful() && $state === GatewayResult::PENDING && ($json['ResponseCode'] ?? null) !== '0001') {
            $state = GatewayResult::FAILED;
        }

        return new GatewayResult(
            $state,
            $payment->client_reference,
            $this->nullableString($data['TransactionId'] ?? null),
            isset($data['Amount']) ? (float) $data['Amount'] : null,
            $json['Message'] ?? $response->reason(),
            $this->scrub($json),
        );
    }

    private function stateFromCode(string $code): string
    {
        return match ($code) {
            '0000' => GatewayResult::PAID,
            '0001' => GatewayResult::PENDING,
            default => GatewayResult::FAILED,
        };
    }

    /**
     * @param  \Closure(PendingRequest): Response  $call
     *
     * @throws GatewayUnavailable on network failures and 5xx answers (outcome unknown)
     */
    private function send(\Closure $call): Response
    {
        $http = Http::withBasicAuth((string) config('hubtel.pay.user'), (string) config('hubtel.pay.key'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('hubtel.pay.timeout', 20));

        try {
            $response = $call($http);
        } catch (ConnectionException $e) {
            throw new GatewayUnavailable('Could not reach Hubtel: '.$e->getMessage(), 0, $e);
        }

        if ($response->serverError()) {
            throw new GatewayUnavailable('Hubtel answered with an error ('.$response->status().').');
        }

        return $response;
    }

    private function url(string $key): string
    {
        return str_replace('{merchant}', (string) config('hubtel.pay.merchant_account'), (string) config("hubtel.pay.{$key}"));
    }

    private function callbackUrl(): string
    {
        return config('hubtel.pay.callback_url')
            ?: route('webhooks.hubtel.payments', ['token' => config('hubtel.pay.callback_token')]);
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /** Payloads are stored for support; make sure no credential-looking field ever lands in the database. */
    private function scrub(array $payload): array
    {
        foreach (array_keys($payload) as $key) {
            if (preg_match('/secret|password|token|authorization|key/i', (string) $key)) {
                $payload[$key] = '[removed]';
            } elseif (is_array($payload[$key])) {
                $payload[$key] = $this->scrub($payload[$key]);
            }
        }

        return $payload;
    }
}
