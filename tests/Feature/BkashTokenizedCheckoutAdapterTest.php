<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\BkashTokenizedCheckoutAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BkashTokenizedCheckoutAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->storeSandboxCredentials();
    }

    public function test_adapter_creates_sandbox_checkout_without_marking_order_paid(): void
    {
        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'sandbox-id-token',
                'expires_in' => 3600,
            ]),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create' => Http::response([
                'paymentID' => 'BKASH-PAY-1001',
                'bkashURL' => 'https://merchantdemo.sandbox.bka.sh/checkout/BKASH-PAY-1001',
            ]),
        ]);

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 1200,
            'discount' => 0,
            'total' => 1200,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
        ]);

        $result = app(BkashTokenizedCheckoutAdapter::class)->initiate($order);

        $this->assertSame('BKASH-PAY-1001', $result['payment_id']);
        $this->assertSame('https://merchantdemo.sandbox.bka.sh/checkout/BKASH-PAY-1001', $result['redirect_url']);
        $this->assertSame('1200.00', $result['amount']);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, $order->payments()->count());

        Http::assertSent(fn ($request) => $request->url() === 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create'
            && $request['merchantInvoiceNumber'] === $order->order_no
            && $request['currency'] === 'BDT'
            && $request['amount'] === '1200.00');
    }

    public function test_callback_verification_executes_and_queries_provider_before_returning_success(): void
    {
        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'sandbox-id-token',
                'expires_in' => 3600,
            ]),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'paymentID' => 'BKASH-PAY-1002',
                'transactionStatus' => 'Completed',
            ]),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/payment/status' => Http::response([
                'paymentID' => 'BKASH-PAY-1002',
                'trxID' => 'BKASH-TRX-2002',
                'merchantInvoiceNumber' => 'AEC-ORDER-1002',
                'amount' => '850.00',
                'currency' => 'BDT',
                'customerMsisdn' => '01712345678',
                'transactionStatus' => 'Completed',
            ]),
        ]);

        $request = Request::create('/payments/bkash/callback/AEC-ORDER-1002', 'GET', [
            'paymentID' => 'BKASH-PAY-1002',
            'status' => 'cancel',
        ]);

        $result = app(BkashTokenizedCheckoutAdapter::class)->verifyCallback($request);

        $this->assertSame('success', $result['status']);
        $this->assertSame('BKASH-TRX-2002', $result['transaction_id']);
        $this->assertSame('AEC-ORDER-1002', $result['invoice']);
        $this->assertSame('850.00', $result['amount']);
        Http::assertSent(fn ($httpRequest) => str_ends_with($httpRequest->url(), '/tokenized/checkout/execute'));
        Http::assertSent(fn ($httpRequest) => str_ends_with($httpRequest->url(), '/tokenized/checkout/payment/status'));
    }

    public function test_callback_settles_the_order_only_after_provider_query_and_is_idempotent(): void
    {
        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'sandbox-id-token',
                'expires_in' => 3600,
            ]),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'paymentID' => 'BKASH-PAY-1003',
                'transactionStatus' => 'Completed',
            ]),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/payment/status' => Http::response([
                'paymentID' => 'BKASH-PAY-1003',
                'trxID' => 'BKASH-TRX-2003',
                'merchantInvoiceNumber' => 'AEC-ORDER-1003',
                'amount' => '500.00',
                'currency' => 'BDT',
                'customerMsisdn' => '01712345678',
                'transactionStatus' => 'Completed',
            ]),
        ]);

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $order = Order::create([
            'order_no' => 'AEC-ORDER-1003',
            'user_id' => $student->id,
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bkash',
            'provider_payment_id' => 'BKASH-PAY-1003',
            'amount' => 500,
            'status' => 'pending',
            'payload' => ['provider' => 'bkash'],
        ]);

        $url = route('payments.bkash.callback', [
            'order' => $order->order_no,
            'paymentID' => 'BKASH-PAY-1003',
            'status' => 'success',
        ]);

        $this->get($url);
        $this->get($url);

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('BKASH-TRX-2003', $payment->fresh()->transaction_id);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(500.0, $order->fresh()->amountPaid());
        Http::assertSentCount(3);
    }

    public function test_adapter_refuses_live_mode_and_non_official_api_hosts(): void
    {
        Setting::set('payment_gateway_credentials.bkash', Crypt::encryptString(json_encode([
            'mode' => 'live',
            'credentials' => [
                'app_key' => 'key',
                'app_secret' => 'secret',
                'username' => 'user',
                'password' => 'password',
            ],
        ])));

        $adapter = app(BkashTokenizedCheckoutAdapter::class);
        $this->assertFalse($adapter->isConfigured());

        $this->expectException(RuntimeException::class);
        $adapter->initiate(new Order([
            'status' => 'pending',
            'total' => 100,
        ]));
    }

    private function storeSandboxCredentials(): void
    {
        Setting::set('payment_gateway_credentials.bkash', Crypt::encryptString(json_encode([
            'mode' => 'sandbox',
            'credentials' => [
                'app_key' => 'sandbox-app-key',
                'app_secret' => 'sandbox-app-secret',
                'username' => 'sandbox-user',
                'password' => 'sandbox-password',
                'base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            ],
        ])));
    }
}
