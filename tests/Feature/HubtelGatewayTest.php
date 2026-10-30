<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\GatewayUnavailable;
use App\Services\Payments\HubtelGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HubtelGatewayTest extends TestCase
{
    private HubtelGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hubtel.pay' => [
            'url' => 'https://pay.example.test/merchants/{merchant}/receive/mobilemoney',
            'status_url' => 'https://status.example.test/transactions/{merchant}/status',
            'user' => 'api-user',
            'key' => 'api-key',
            'merchant_account' => 'HM123456',
            'callback_url' => null,
            'callback_token' => 'cb-secret',
            'verify_with_status' => true,
            'timeout' => 5,
            'expire_minutes' => 5,
        ]]);

        $this->gateway = new HubtelGateway;
    }

    private function payment(string $phone = '+233241234567', string $channel = 'mtn-gh'): array
    {
        $order = new Order(['code' => 'ORD-0007', 'customer_name' => 'Kwame Mensah']);
        $payment = new OrderPayment(['client_reference' => 'TRD7-ABCD1234', 'phone' => $phone, 'channel' => $channel, 'amount' => '60.00']);

        return [$payment, $order];
    }

    public function test_it_needs_url_credentials_and_merchant_account_to_count_as_configured(): void
    {
        $this->assertTrue($this->gateway->isConfigured());
        $this->assertTrue($this->gateway->canVerify());

        foreach (['url', 'user', 'key', 'merchant_account'] as $missing) {
            config(["hubtel.pay.{$missing}" => null]);
            $this->assertFalse((new HubtelGateway)->isConfigured(), "{$missing} missing");
            config(["hubtel.pay.{$missing}" => 'x']);
        }

        config(['hubtel.pay.status_url' => null]);
        $this->assertFalse((new HubtelGateway)->canVerify());
    }

    public function test_charge_sends_the_documented_request(): void
    {
        Http::fake(['pay.example.test/*' => Http::response([
            'ResponseCode' => '0001', 'Message' => 'Transaction pending. Expect callback request for final state',
            'Data' => ['TransactionId' => 'T-99', 'ClientReference' => 'TRD7-ABCD1234', 'Amount' => 60.0],
        ], 200)]);

        [$payment, $order] = $this->payment();
        $result = $this->gateway->charge($payment, $order);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://pay.example.test/merchants/HM123456/receive/mobilemoney'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('api-user:api-key'))
                && $request['CustomerName'] === 'Kwame Mensah'
                && $request['CustomerMsisdn'] === '233241234567'
                && $request['Channel'] === 'mtn-gh'
                && $request['Amount'] === 60.0
                && $request['ClientReference'] === 'TRD7-ABCD1234'
                && str_contains($request['Description'], 'ORD-0007')
                && $request['PrimaryCallbackUrl'] === url('webhooks/hubtel/payments/cb-secret');
        });

        $this->assertTrue($result->isPending());
        $this->assertSame('T-99', $result->providerReference);
        $this->assertSame('TRD7-ABCD1234', $result->clientReference);
    }

    public function test_an_explicit_callback_url_overrides_the_default(): void
    {
        config(['hubtel.pay.callback_url' => 'https://tunnel.example.test/hook']);
        Http::fake(['pay.example.test/*' => Http::response(['ResponseCode' => '0001'], 200)]);

        [$payment, $order] = $this->payment();
        $this->gateway->charge($payment, $order);

        Http::assertSent(fn (Request $r) => $r['PrimaryCallbackUrl'] === 'https://tunnel.example.test/hook');
    }

    public function test_charge_response_codes_map_to_our_states(): void
    {
        [$payment, $order] = $this->payment();

        Http::fake(['pay.example.test/*' => Http::sequence()
            ->push(['ResponseCode' => '0000', 'Data' => ['Amount' => 60]], 200)
            ->push(['ResponseCode' => '2001', 'Message' => 'Insufficient funds'], 200)
            // A 4xx without a recognised success code is a refusal, never "pending".
            ->push(['Message' => 'Invalid channel'], 400)]);

        $this->assertTrue($this->gateway->charge($payment, $order)->isPaid());

        $declined = $this->gateway->charge($payment, $order);
        $this->assertTrue($declined->isFailed());
        $this->assertSame('Insufficient funds', $declined->message);

        $this->assertTrue($this->gateway->charge($payment, $order)->isFailed());
    }

    public function test_server_errors_and_network_failures_are_reported_as_unknown_outcomes(): void
    {
        [$payment, $order] = $this->payment();

        Http::fake(['pay.example.test/*' => Http::sequence()
            ->push('Bad gateway', 502)
            ->pushFailedConnection('timed out')]);

        foreach (['5xx', 'timeout'] as $case) {
            try {
                $this->gateway->charge($payment, $order);
                $this->fail("{$case} must not be treated as a clean failure");
            } catch (GatewayUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_status_maps_paid_unpaid_and_refunded(): void
    {
        [$payment] = $this->payment();

        Http::fake(['status.example.test/*' => Http::sequence()
            ->push(['responseCode' => '0000', 'data' => ['status' => 'Paid', 'transactionId' => 'T-1', 'amount' => 60.0]], 200)
            ->push(['data' => ['status' => 'Unpaid']], 200)
            ->push(['data' => ['status' => 'Refunded']], 200)
            ->push('not found', 404)]);

        $paid = $this->gateway->status($payment);
        $this->assertTrue($paid->isPaid());
        $this->assertSame(60.0, $paid->amount);
        $this->assertSame('T-1', $paid->providerReference);

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && str_starts_with($r->url(), 'https://status.example.test/transactions/HM123456/status')
            && str_contains($r->url(), 'clientReference=TRD7-ABCD1234')
            && $r->hasHeader('Authorization'));

        $this->assertTrue($this->gateway->status($payment)->isPending());   // Unpaid
        $this->assertTrue($this->gateway->status($payment)->isFailed());    // Refunded
        $this->assertTrue($this->gateway->status($payment)->isPending());   // 404: unknown, keep waiting
    }

    public function test_status_without_an_endpoint_just_says_pending_and_makes_no_request(): void
    {
        config(['hubtel.pay.status_url' => null]);
        Http::fake();

        [$payment] = $this->payment();

        $this->assertTrue((new HubtelGateway)->status($payment)->isPending());
        Http::assertNothingSent();
    }

    public function test_callbacks_are_parsed_and_scrubbed(): void
    {
        $result = $this->gateway->parseCallback([
            'ResponseCode' => '0000',
            'Message' => 'success',
            'ApiSecret' => 'should-never-be-stored',
            'Data' => ['ClientReference' => 'TRD7-ABCD1234', 'TransactionId' => 'T-5', 'Amount' => 60.0, 'AccessToken' => 'also-secret'],
        ]);

        $this->assertTrue($result->isPaid());
        $this->assertSame('TRD7-ABCD1234', $result->clientReference);
        $this->assertSame('T-5', $result->providerReference);
        $this->assertSame(60.0, $result->amount);
        $this->assertSame('[removed]', $result->raw['ApiSecret']);
        $this->assertSame('[removed]', $result->raw['Data']['AccessToken']);

        $this->assertSame(GatewayResult::FAILED, $this->gateway->parseCallback(['ResponseCode' => '2001', 'Data' => ['ClientReference' => 'x']])->state);
        $this->assertSame(GatewayResult::PENDING, $this->gateway->parseCallback(['ResponseCode' => '0001', 'Data' => ['ClientReference' => 'x']])->state);
    }

    public function test_channels_follow_the_network_prefix(): void
    {
        $this->assertSame('mtn-gh', HubtelGateway::channelFor('0241234567'));
        $this->assertSame('mtn-gh', HubtelGateway::channelFor('0551234567'));
        $this->assertSame('vodafone-gh', HubtelGateway::channelFor('0201234567'));
        $this->assertSame('vodafone-gh', HubtelGateway::channelFor('0501234567'));
        $this->assertSame('tigo-gh', HubtelGateway::channelFor('0271234567'));
        $this->assertSame('tigo-gh', HubtelGateway::channelFor('0571234567'));
        $this->assertNull(HubtelGateway::channelFor('0301234567'));
        $this->assertNull(HubtelGateway::channelFor('nonsense'));
    }
}
