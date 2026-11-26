<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\BkashCheckoutAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BkashWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $certificate = '';
    private string $privateKey = '';
    private string $topicArn = 'arn:aws:sns:ap-southeast-1:123456789012:aec-sandbox-topic';
    private string $certificateUrl = 'https://sns.ap-southeast-1.amazonaws.com/SimpleNotificationService-test.pem';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'SNS test signer'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 1);
        openssl_x509_export($cert, $this->certificate);
        openssl_pkey_export($key, $this->privateKey);

        Setting::set('payment_gateway_credentials.bkash', Crypt::encryptString(json_encode([
            'mode' => 'sandbox',
            'credentials' => [
                'app_key' => 'sandbox-app-key',
                'app_secret' => 'sandbox-app-secret',
                'username' => 'sandbox-user',
                'password' => 'sandbox-password',
                'webhook_topic_arn' => $this->topicArn,
            ],
        ])));
    }

    public function test_signed_sns_notification_is_reconciled_with_bkash_before_order_settlement(): void
    {
        Http::fake([
            $this->certificateUrl => Http::response($this->certificate, 200),
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/token/grant' => Http::response([
                'id_token' => 'sandbox-id-token',
                'expires_in' => 3600,
            ]),
            'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout/payment/search/*' => Http::response([
                'trxID' => 'BKASH-WEBHOOK-TRX-1',
                'merchantInvoiceNumber' => 'AEC-WEBHOOK-ORDER-1',
                'amount' => '500.00',
                'currency' => 'BDT',
                'customerMsisdn' => '01712345678',
                'transactionStatus' => 'Completed',
            ]),
        ]);

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $order = Order::create([
            'order_no' => 'AEC-WEBHOOK-ORDER-1',
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
            'provider_payment_id' => 'BKASH-PAY-WEBHOOK-1',
            'amount' => 500,
            'status' => 'pending',
            'payload' => ['provider' => 'bkash'],
        ]);

        $event = $this->signedNotification([
            'dateTime' => '20261126210000',
            'debitMSISDN' => '8801700000001',
            'trxID' => 'BKASH-WEBHOOK-TRX-1',
            'transactionStatus' => 'Completed',
            'amount' => '500.00',
            'currency' => 'BDT',
            'merchantInvoiceNumber' => 'AEC-WEBHOOK-ORDER-1',
        ]);

        $this->call('POST', route('payments.bkash.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($event))->assertNoContent();

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('BKASH-WEBHOOK-TRX-1', $payment->fresh()->transaction_id);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(500.0, $order->fresh()->amountPaid());

        // SNS may redeliver the same event. It must not settle the payment twice.
        $this->call('POST', route('payments.bkash.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($event))->assertNoContent();

        $this->assertSame('verified', $payment->fresh()->status);
        Http::assertSentCount(4);
    }

    public function test_stale_failed_browser_callback_cannot_overwrite_webhook_settlement(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $order = Order::create([
            'order_no' => 'AEC-CALLBACK-RACE-1',
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
            'provider_payment_id' => 'BKASH-PAY-CALLBACK-RACE-1',
            'amount' => 500,
            'status' => 'pending',
            'payload' => ['provider' => 'bkash'],
        ]);

        $this->mock(BkashCheckoutAdapter::class)
            ->shouldReceive('verifyCallback')
            ->once()
            ->andReturnUsing(function () use ($payment) {
                // Simulate a webhook settling the payment while the browser return is in flight.
                Payment::whereKey($payment->id)->update([
                    'transaction_id' => 'BKASH-WEBHOOK-RACE-TRX',
                    'status' => 'verified',
                    'verified_at' => now(),
                ]);

                return ['status' => 'failed', 'reason' => 'Stale browser response'];
            });

        $this->get(route('payments.bkash.callback', ['paymentID' => 'BKASH-PAY-CALLBACK-RACE-1']))
            ->assertRedirect(route('checkout.success', $order->order_no));

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('BKASH-WEBHOOK-RACE-TRX', $payment->fresh()->transaction_id);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_invalid_sns_signature_is_rejected_without_calling_payment_api(): void
    {
        Http::fake([$this->certificateUrl => Http::response($this->certificate, 200)]);

        $event = $this->signedNotification([
            'trxID' => 'BKASH-WEBHOOK-TRX-BAD',
            'transactionStatus' => 'Completed',
            'amount' => '10.00',
            'currency' => 'BDT',
            'merchantInvoiceNumber' => 'AEC-ORDER-BAD',
        ]);
        $event['Message'] = json_encode(['trxID' => 'TAMPERED']);

        $this->call('POST', route('payments.bkash.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($event))->assertForbidden();

        Http::assertSentCount(1);
    }

    public function test_adapter_refuses_webhook_topic_mismatch(): void
    {
        Http::fake();
        $event = $this->signedNotification([
            'trxID' => 'BKASH-WEBHOOK-TRX-3',
            'transactionStatus' => 'Completed',
            'amount' => '10.00',
            'currency' => 'BDT',
            'merchantInvoiceNumber' => 'AEC-ORDER-3',
        ]);
        $event['TopicArn'] = 'arn:aws:sns:ap-southeast-1:123456789012:other-topic';

        $this->expectException(ValidationException::class);
        app(BkashCheckoutAdapter::class)->verifyWebhook(
            request()->create('/payments/bkash/webhook', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode($event))
        );
    }

    private function signedNotification(array $message): array
    {
        $event = [
            'Type' => 'Notification',
            'MessageId' => 'sns-message-'.uniqid(),
            'TopicArn' => $this->topicArn,
            'Message' => json_encode($message, JSON_THROW_ON_ERROR),
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1',
            'SigningCertURL' => $this->certificateUrl,
        ];

        $canonical = '';
        foreach (['Message', 'MessageId', 'Timestamp', 'TopicArn', 'Type'] as $field) {
            $canonical .= $field."\n".$event[$field]."\n";
        }

        openssl_sign($canonical, $signature, $this->privateKey, OPENSSL_ALGO_SHA1);
        $event['Signature'] = base64_encode($signature);

        return $event;
    }
}
