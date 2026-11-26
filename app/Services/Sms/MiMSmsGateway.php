<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MiMSmsGateway
{
    /**
     * Convert common Bangladesh mobile formats to international 8801XXXXXXXXX.
     */
    public function normalizeBangladeshNumber(?string $number): ?string
    {
        if (! is_string($number) || trim($number) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $number);

        if (str_starts_with($digits, '00880')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '88'.$digits;
        }

        return preg_match('/^8801[3-9][0-9]{8}$/', $digits) ? $digits : null;
    }

    /**
     * Send one transactional SMS. MiMSMS transactional requests accept one recipient.
     *
     * @return array{tracking_id: ?string, response: array}
     */
    public function sendTransactional(string $mobileNumber, string $message): array
    {
        $config = config('live_classes.sms');
        foreach (['username', 'api_key', 'sender_name', 'endpoint'] as $key) {
            if (blank($config[$key] ?? null)) {
                throw new RuntimeException('MiMSMS is not configured: missing '.$key.'.');
            }
        }

        $number = $this->normalizeBangladeshNumber($mobileNumber);
        if (! $number) {
            throw new RuntimeException('The recipient is not a valid Bangladesh mobile number.');
        }

        if (trim($message) === '') {
            throw new RuntimeException('SMS message cannot be empty.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout(max(1, (int) ($config['connect_timeout'] ?? 5)))
            ->timeout(max(1, (int) ($config['timeout'] ?? 15)))
            ->post($config['endpoint'], [
                'apiKey' => $config['api_key'],
                'userName' => $config['username'],
                'senderName' => $config['sender_name'],
                'transactionType' => 'T',
                'mobileNumber' => $number,
                'message' => $message,
            ]);

        $payload = $response->json();
        if (! $response->successful()
            || ! is_array($payload)
            || (string) ($payload['statusCode'] ?? '') !== '200'
            || strcasecmp((string) ($payload['status'] ?? ''), 'success') !== 0) {
            throw new RuntimeException('MiMSMS rejected the SMS request (HTTP '.$response->status().').');
        }

        return [
            'tracking_id' => isset($payload['trxnId']) ? (string) $payload['trxnId'] : null,
            'response' => $payload,
        ];
    }
}
