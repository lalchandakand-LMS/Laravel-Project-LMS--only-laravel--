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
        $response = $this->api($config)->post(self::CREATE_PATH, [
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

    private function providerPost(string $path, array $payload): array
    {
        $response = $this->api($this->configuration())->post($path, $payload)->throw();
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
        $cacheKey = 'payments:bkash:token:'.hash('sha256', $credentials['app_key'].'|'.$credentials['app_secret'].'|'.$credentials['username']);
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
            && is_string($parts['host'] ?? null)
            && (str_ends_with($parts['host'], '.bka.sh') || $parts['host'] === 'bka.sh')
            && ! isset($parts['user']) && ! isset($parts['pass']);
    }
}
