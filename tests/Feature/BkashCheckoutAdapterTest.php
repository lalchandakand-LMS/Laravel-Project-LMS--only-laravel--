<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\BkashCheckoutAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BkashCheckoutAdapterTest extends TestCase
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
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/token/grant' => Http::response([
                'id_token' => 'sandbox-id-token',
                'expires_in' => 3600,
            ]),
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/payment/create' => Http::response([
                'paymentID' => 'BKASH-PAY-1001',
                'bkashURL' => 'https://sandbox.payment.bkash.com/redirect/tokenized/?paymentID=BKASH-PAY-1001&hash=test',
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

        $result = app(BkashCheckoutAdapter::class)->initiate($order);

        $this->assertSame('BKASH-PAY-1001', $result['payment_id']);
        $this->assertSame('https://sandbox.payment.bkash.com/redirect/tokenized/?paymentID=BKASH-PAY-1001&hash=test', $result['redirect_url']);
        $this->assertSame('1200.00', $result['amount']);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, $order->payments()->count());

        Http::assertSent(fn ($request) => $request->url() === 'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/payment/create'
            && $request['merchantInvoiceNumber'] === $order->order_no
            && $request['currency'] === 'BDT'
            && $request['amount'] === '1200.00');
    }

    public function test_checkout_initiation_persists_one_pending_session_before_another_request_can_start(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $branch = Branch::create([
            'name' => 'bKash Checkout Branch',
            'code' => 'BKASH-CHECKOUT-TEST',
            'is_active' => true,
            'payment_methods' => ['bkash'],
        ]);
        $order = Order::create([
            'order_no' => 'AEC-BKASH-LOCK-01',
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 600,
            'discount' => 0,
            'total' => 600,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
        ]);

        $this->mock(BkashCheckoutAdapter::class)
            ->shouldReceive('initiate')
            ->once()
            ->andReturn([
                'payment_id' => 'BKASH-PAY-LOCK-01',
                'redirect_url' => 'https://sandbox.payment.bkash.com/redirect/tokenized/?paymentID=BKASH-PAY-LOCK-01',
                'amount' => '600.00',
                'currency' => 'BDT',
                'invoice' => $order->order_no,
            ]);

        $this->actingAs($student)
            ->post(route('checkout.bkash', $order->order_no))
            ->assertRedirect('https://sandbox.payment.bkash.com/redirect/tokenized/?paymentID=BKASH-PAY-LOCK-01');

        $this->assertSame(1, $order->payments()->where('status', 'pending')->count());
        $this->assertSame('BKASH-PAY-LOCK-01', $order->payments()->first()->provider_payment_id);

        $this->actingAs($student)
            ->from(route('checkout.success', $order->order_no))
            ->post(route('checkout.bkash', $order->order_no))
            ->assertRedirect(route('checkout.success', $order->order_no))
            ->assertSessionHasErrors('payment');

        $this->assertSame(1, $order->payments()->where('status', 'pending')->count());
    }

    public function test_callback_verification_executes_and_queries_provider_before_returning_success(): void
    {
        Http::fake([
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/token/grant' => Http::response([
                'id_token' => 'sandbox-id-token',
                'expires_in' => 3600,
            ]),
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/payment/execute/*' => Http::response([
                'paymentID' => 'BKASH-PAY-1002',
                'transactionStatus' => 'Completed',
            ]),
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/payment/query/*' => Http::response([
                'paymentID' => 'BKASH-PAY-1002',
                'trxID' => 'BKASH-TRX-2002',
                'merchantInvoiceNumber' => 'AEC-ORDER-1002',
                'amount' => '850.00',
                'currency' => 'BDT',
                'customerMsisdn' => '01712345678',
                'transactionStatus' => 'Completed',
            ]),
        ]);

        $request = Request::create('/payments/bkash/callback', 'GET', [
            'paymentID' => 'BKASH-PAY-1002',
            'status' => 'cancel',
        ]);

        $result = app(BkashCheckoutAdapter::class)->verifyCallback($request);

        $this->assertSame('success', $result['status']);
        $this->assertSame('BKASH-TRX-2002', $result['transaction_id']);
        $this->assertSame('AEC-ORDER-1002', $result['invoice']);
        $this->assertSame('850.00', $result['amount']);
        Http::assertSent(fn ($httpRequest) => str_contains($httpRequest->url(), '/checkout/payment/execute/BKASH-PAY-1002'));
        Http::assertSent(fn ($httpRequest) => str_contains($httpRequest->url(), '/checkout/payment/query/BKASH-PAY-1002'));
    }

    public function test_callback_settles_the_order_only_after_provider_query_and_is_idempotent(): void
    {
        Http::fake([
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/token/grant' => Http::response([
                'id_token' => 'sandbox-id-token',
                'expires_in' => 3600,
            ]),
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/payment/execute/*' => Http::response([
                'paymentID' => 'BKASH-PAY-1003',
                'transactionStatus' => 'Completed',
            ]),
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/payment/query/*' => Http::response([
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

        $adapter = app(BkashCheckoutAdapter::class);
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
                'base_url' => 'https://checkout.sandbox.bka.sh/v1.2.0-beta',
            ],
        ])));
    }
}
