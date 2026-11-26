<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Setting;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Crypt;
use Illuminate\\Support\\Facades\\DB;
use Throwable;

class PaymentMethodController extends Controller
{
    private const METHODS = [
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'rocket' => 'Rocket',
        'bank' => 'Bank transfer',
    ];

    private const PROVIDERS = [
        'bkash' => [
            'label' => 'bKash',
            'fields' => ['merchant_number' => 'Merchant number', 'app_key' => 'App key', 'app_secret' => 'App secret', 'username' => 'API username', 'password' => 'API password', 'base_url' => 'API base URL', 'webhook_secret' => 'Webhook secret'],
        ],
        'nagad' => [
            'label' => 'Nagad',
            'fields' => ['merchant_id' => 'Merchant ID', 'merchant_number' => 'Merchant number', 'public_key' => 'Public key', 'private_key' => 'Private key', 'base_url' => 'API base URL', 'webhook_secret' => 'Webhook secret'],
        ],
        'rocket' => [
            'label' => 'Rocket',
            'fields' => ['merchant_id' => 'Merchant ID', 'merchant_number' => 'Merchant number', 'api_key' => 'API key', 'api_secret' => 'API secret', 'base_url' => 'API base URL', 'webhook_secret' => 'Webhook secret'],
        ],
    ];

    public function index()
    {
        $storedMethods = Setting::get('payment_methods.global_enabled');
        $enabledMethods = $storedMethods === null
            ? array_keys(self::METHODS)
            : (json_decode((string) $storedMethods, true) ?: []);

        $providers = [];
        foreach (self::PROVIDERS as $key => $provider) {
            $credentials = $this->credentials($key);
            $providers[$key] = [
                ...$provider,
                'mode' => $credentials['mode'] ?? 'sandbox',
                'saved' => collect($credentials['credentials'] ?? [])->filter(fn ($value) => filled($value))->isNotEmpty(),
            ];
        }

        return view('admin.payment-methods.index', [
            'methods' => self::METHODS,
            'enabledMethods' => $enabledMethods,
            'providers' => $providers,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'methods' => ['nullable', 'array'],
            'methods.*' => ['string', 'in:'.implode(',', array_keys(self::METHODS))],
            'providers' => ['nullable', 'array'],
            'providers.*.mode' => ['required', 'in:sandbox,live'],
            'providers.*.credentials' => ['nullable', 'array'],
            'providers.*.credentials.*' => ['nullable', 'string', 'max:10000'],
            'providers.*.clear_credentials' => ['nullable', 'boolean'],
        ]);

        $submittedProviders = $request->input('providers', []);
        foreach (array_keys($submittedProviders) as $provider) {
            if (! array_key_exists($provider, self::PROVIDERS)) {
                abort(422, 'Unknown payment provider.');
            }
        }

        DB::transaction(function () use ($request, $submittedProviders) {
            $enabled = array_values(array_unique($request->input('methods', [])));
            Setting::set('payment_methods.global_enabled', json_encode($enabled, JSON_THROW_ON_ERROR));

            foreach ($submittedProviders as $provider => $input) {
                $current = $this->credentials($provider);
                $credentials = ($request->boolean("providers.$provider.clear_credentials"))
                    ? []
                    : ($current['credentials'] ?? []);

                foreach (array_keys(self::PROVIDERS[$provider]['fields']) as $field) {
                    $value = data_get($input, "credentials.$field");
                    if (filled($value)) {
                        $credentials[$field] = trim($value);
                    }
                }

                $payload = [
                    'mode' => $input['mode'],
                    'credentials' => $credentials,
                ];

                Setting::set(
                    "payment_gateway_credentials.$provider",
                    Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR))
                );
            }
        });

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Payment method settings saved. Gateway API processing remains inactive until its adapter and provider tests are completed.');
    }

    private function credentials(string $provider): array
    {
        $encrypted = Setting::get("payment_gateway_credentials.$provider");
        if (! is_string($encrypted) || $encrypted === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }
}
