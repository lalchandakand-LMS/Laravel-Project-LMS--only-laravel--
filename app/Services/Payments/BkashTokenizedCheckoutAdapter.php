<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayAdapter;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BkashTokenizedCheckoutAdapter implements PaymentGatewayAdapter
{
    private const TOKEN_PATH = '/tokenized/checkout/token/grant';
    private const CREATE_PATH = '/tokenized/checkout/create';
    private const EXECUTE_PATH = '/tokenized/checkout/execute';
    private const STATUS_PATH = '/tokenized/checkout/payment/status';

    public function isConfigured(): bool
    {
        try {
            $credentials = $this->configuration()['credentials'];

            return filled($credentials['app_key'] ?? null)
                && filled($credentials['app_secret'] ?? null)
                && filled($credentials['username'] ?? null)
                && filled($credentials['password'] ?? null);
        } catch (Throwable) {
            return false;
        }
    }

    public function initiate(Order $order, array $context = []): array
    {
        if (! in_array($order->status, ['pending', 'partially_paid'], true) || $order->dueAmount() <= 0) {
            throw ValidationException::withMessages(['payment' => 'This order is not awaiting a bKash payment.']);
        }

        $config = $this->configuration();
        $response = $this->api($config)->post($config['base_url'].self::CREATE_PATH, [
            'mode' => '0011',
            'payerReference' => substr((string) $order->order_no, 0, 30),
            'callbackURL' => route('payments.bkash.callback', ['order' => $order->order_no]),
            'amount' => number_format($order->dueAmount(), 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => (string) $order->order_no,
        ])->throw()->json();

        $paymentId = (string) ($response['paymentID'] ?? '');
        $redirectUrl = (string) ($response['bkashURL'] ?? '');

        if ($paymentId === '' || ! $this->isTrustedCheckoutUrl($redirectUrl)) {
            throw new RuntimeException('bKash did not return a valid payment session. No order payment was confirmed.');
        }

        return [
            'payment_id' => $paymentId,
            'redirect_url' => $redirectUrl,
            'amount' => number_format($order->dueAmount(), 2, '.', ''),
            'currency' => 'BDT',
            'invoice' => $order->order_no,
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $paymentId = trim((string) ($request->input('paymentID') ?: $request->query('paymentID')));
        if ($paymentId === '' || strlen($paymentId) > 100) {
            throw ValidationException::withMessages(['payment' => 'The bKash callback did not include a valid payment reference.']);
        }

        // Ignore browser-supplied status. The provider-side execute/query result is authoritative.

        // Execute finalizes the provider-side session. If it reports an already
        // executed/uncertain result, the independent status query is authoritative.
        try {
            $this->providerPost(self::EXECUTE_PATH, ['paymentID' => $paymentId]);
        } catch (Throwable) {
            // A retry or delayed redirect can arrive after the provider executed it.
            // Never treat this exception as success; continue only to server-side query.
        }

        return $this->queryTransaction($paymentId);
    }

    public function queryTransaction(string $providerTransactionId): array
    {
        $paymentId = trim($providerTransactionId);
        if ($paymentId === '' || strlen($paymentId) > 100) {
            throw new RuntimeException('A valid bKash payment reference is required for status reconciliation.');
        }

        $result = $this->providerPost(self::STATUS_PATH, ['paymentID' => $paymentId]);
        $providerStatus = strtolower((string) ($result['transactionStatus'] ?? ''));
        $completed = $providerStatus === 'completed';
        $failed = in_array($providerStatus, ['failed', 'cancelled', 'canceled', 'rejected', 'expired'], true);

        return [
            'status' => $completed ? 'success' : ($failed ? 'failed' : 'pending'),
            'payment_id' => (string) ($result['paymentID'] ?? $paymentId),
            'transaction_id' => (string) ($result['trxID'] ?? ''),
            'invoice' => (string) ($result['merchantInvoiceNumber'] ?? ''),
            'amount' => (string) ($result['amount'] ?? ''),
            'currency' => strtoupper((string) ($result['currency'] ?? '')),
            'customer_msisdn' => (string) ($result['customerMsisdn'] ?? ''),
            'provider_status' => (string) ($result['transactionStatus'] ?? ''),
            'reason' => $failed ? 'bKash reported '.$providerStatus.'.' : null,
        ];
    }

    /** Verify an AWS SNS envelope using the signing certificate URL supplied by bKash. */
    public function verifyWebhook(Request $request): array
    {
        $event = json_decode($request->getContent(), true);
        if (! is_array($event)) {
            throw ValidationException::withMessages(['webhook' => 'Invalid bKash webhook payload.']);
        }

        $config = $this->configuration();
        $expectedTopic = (string) ($config['credentials']['webhook_topic_arn'] ?? '');
        if ($expectedTopic === '' || ! hash_equals($expectedTopic, (string) ($event['TopicArn'] ?? ''))) {
            throw ValidationException::withMessages(['webhook' => 'The bKash webhook topic is not configured or does not match.']);
        }

        $type = (string) ($event['Type'] ?? '');
        if (! in_array($type, ['Notification', 'SubscriptionConfirmation', 'UnsubscribeConfirmation'], true)) {
            throw ValidationException::withMessages(['webhook' => 'Unsupported SNS message type.']);
        }

        $region = $this->snsRegion($expectedTopic);
        $certificateUrl = (string) ($event['SigningCertURL'] ?? '');
        $certificate = $this->fetchSnsCertificate($certificateUrl, $region);
        $fields = $type === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];

        $canonical = '';
        foreach ($fields as $field) {
            if ($field === 'Subject' && ! array_key_exists($field, $event)) {
                continue;
            }
            if (! array_key_exists($field, $event) || ! is_scalar($event[$field])) {
                throw ValidationException::withMessages(['webhook' => 'The signed SNS message is missing a required field.']);
            }
            $canonical .= $field."\n".(string) $event[$field]."\n";
        }

        $signature = base64_decode((string) ($event['Signature'] ?? ''), true);
        $algorithm = match ((string) ($event['SignatureVersion'] ?? '')) {
            '1' => OPENSSL_ALGO_SHA1,
            '2' => OPENSSL_ALGO_SHA256,
            default => null,
        };
        $publicKey = openssl_pkey_get_public($certificate);
        if ($signature === false || $algorithm === null || $publicKey === false
            || openssl_verify($canonical, $signature, $publicKey, $algorithm) !== 1) {
            throw ValidationException::withMessages(['webhook' => 'The bKash SNS signature could not be verified.']);
        }

        return $event;
    }

    public function confirmWebhookSubscription(array $event): void
    {
        $topic = (string) ($event['TopicArn'] ?? '');
        $region = $this->snsRegion($topic);
        $url = (string) ($event['SubscribeURL'] ?? '');
        $parts = parse_url($url);
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'sns.'.$region.'.amazonaws.com'
            || ($parts['path'] ?? '/') !== '/'
            || ($query['Action'] ?? null) !== 'ConfirmSubscription'
            || ($query['TopicArn'] ?? null) !== $topic) {
            throw ValidationException::withMessages(['webhook' => 'The SNS subscription confirmation URL is not trusted.']);
        }

        Http::timeout(10)->get($url)->throw();
    }

    /** Search by bKash transaction ID after a signed webhook notification. */
    public function searchTransaction(string $transactionId): array
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '' || strlen($transactionId) > 100) {
            throw new RuntimeException('A valid bKash transaction ID is required.');
        }

        $result = $this->providerPost('/tokenized/checkout/general/searchTransaction', [
            'trxID' => $transactionId,
        ]);
        $providerStatus = strtolower((string) ($result['transactionStatus'] ?? ''));
        $completed = $providerStatus === 'completed';

        return [
            'status' => $completed ? 'success' : (in_array($providerStatus, ['failed', 'cancelled', 'canceled', 'rejected'], true) ? 'failed' : 'pending'),
            'payment_id' => (string) ($result['paymentID'] ?? ''),
            'transaction_id' => (string) ($result['trxID'] ?? $transactionId),
            'invoice' => (string) ($result['merchantInvoiceNumber'] ?? ''),
            'amount' => (string) ($result['amount'] ?? ''),
            'currency' => strtoupper((string) ($result['currency'] ?? '')),
            'customer_msisdn' => (string) ($result['customerMsisdn'] ?? ''),
            'provider_status' => (string) ($result['transactionStatus'] ?? ''),
        ];
    }

    private function snsRegion(string $topicArn): string
    {
        if (! preg_match('/^arn:aws:sns:([a-z0-9-]+):[0-9]{12}:[A-Za-z0-9_-]+$/', $topicArn, $matches)) {
            throw ValidationException::withMessages(['webhook' => 'The configured bKash SNS topic ARN is invalid.']);
        }

        return $matches[1];
    }

    private function fetchSnsCertificate(string $url, string $region): string
    {
        $parts = parse_url($url);
        $expectedHost = 'sns.'.$region.'.amazonaws.com';
        if (($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== $expectedHost
            || ! preg_match('#^/SimpleNotificationService-[A-Za-z0-9-]+\\.pem$#', (string) ($parts['path'] ?? ''))
            || isset($parts['query']) || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['webhook' => 'The SNS signing certificate URL is not trusted.']);
        }

        return Cache::remember('payments:bkash:sns-cert:'.hash('sha256', $url), now()->addDay(), function () use ($url) {
            $certificate = Http::timeout(10)->get($url)->throw()->body();
            if (! str_contains($certificate, 'BEGIN CERTIFICATE')) {
                throw new RuntimeException('The SNS signing certificate response was invalid.');
            }

            return $certificate;
        });
    }

    private function providerPost(string $path, array $payload): array
    {
        $configuration = $this->configuration();
        $response = $this->api($configuration)->post($configuration['base_url'].$path, $payload)->throw();
        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('bKash returned an unreadable response.');
        }

        return $json;
    }

    private function api(array $configuration): PendingRequest
    {
        $credentials = $configuration['credentials'];

        return Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->withHeaders([
                'Authorization' => $this->token($configuration),
                'X-App-Key' => $credentials['app_key'],
            ]);
    }

    private function token(array $configuration): string
    {
        $credentials = $configuration['credentials'];
        $cacheKey = 'payments:bkash:token:'.hash('sha256', $credentials['app_key'].'|'.$credentials['app_secret'].'|'.$credentials['username'].'|'.$credentials['password']);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return Cache::lock($cacheKey.':lock', 45)->block(45, function () use ($cacheKey, $credentials, $configuration) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->withHeaders([
                    'username' => $credentials['username'],
                    'password' => $credentials['password'],
                    'X-App-Key' => $credentials['app_key'],
                ])
                ->post($configuration['base_url'].self::TOKEN_PATH, [
                    'app_key' => $credentials['app_key'],
                    'app_secret' => $credentials['app_secret'],
                ])->throw()->json();

            $token = (string) ($response['id_token'] ?? '');
            if ($token === '') {
                throw new RuntimeException('bKash authentication did not return an access token.');
            }

            $ttl = max(60, (int) ($response['expires_in'] ?? 3600) - 60);
            Cache::put($cacheKey, $token, now()->addSeconds($ttl));

            return $token;
        });
    }

    private function configuration(): array
    {
        $encrypted = Setting::get('payment_gateway_credentials.bkash');
        if (! is_string($encrypted) || $encrypted === '') {
            throw new RuntimeException('bKash sandbox credentials have not been configured by an administrator.');
        }

        try {
            $stored = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Saved bKash credentials could not be decrypted. Check the application key configuration.');
        }

        if (($stored['mode'] ?? 'sandbox') !== 'sandbox') {
            throw new RuntimeException('bKash Live mode is disabled until production certification is approved.');
        }

        $credentials = $stored['credentials'] ?? [];
        foreach (['app_key', 'app_secret', 'username', 'password'] as $required) {
            if (! is_string($credentials[$required] ?? null) || trim($credentials[$required]) === '') {
                throw new RuntimeException('Required bKash sandbox credentials are incomplete.');
            }
        }

        $baseUrl = rtrim((string) ($credentials['base_url'] ?? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'), '/');
        $parts = parse_url($baseUrl);
        if (($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'tokenized.sandbox.bka.sh'
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['port']) && (int) $parts['port'] !== 443) {
            throw new RuntimeException('Only the official HTTPS bKash sandbox API host is permitted.');
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if ($path !== '' && $path !== '/v1.2.0-beta') {
            throw new RuntimeException('Unsupported bKash API path. Use the approved Tokenized Checkout v1.2.0-beta sandbox base URL.');
        }

        return [
            'credentials' => $credentials,
            'base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
        ];
    }

    private function isTrustedCheckoutUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? null, [
                'tokenized.sandbox.bka.sh',
                'merchantdemo.sandbox.bka.sh',
            ], true)
            && ! isset($parts['user']) && ! isset($parts['pass']);
    }
}
