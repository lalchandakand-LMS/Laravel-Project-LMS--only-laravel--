<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleMarketingReporting
{
    public function credentialsConfigured(): bool
    {
        return filled(config('services.google_reporting.client_id'))
            && filled(config('services.google_reporting.client_secret'));
    }

    public function connected(): bool
    {
        $encrypted = Setting::get('google_oauth_refresh_token');
        if (! is_string($encrypted) || $encrypted === '') {
            return false;
        }

        try {
            return filled(Crypt::decryptString($encrypted));
        } catch (\Throwable) {
            return false;
        }
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        if (! $this->credentialsConfigured()) {
            throw new RuntimeException('Google OAuth credentials are not configured.');
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_reporting.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/webmasters.readonly',
                'https://www.googleapis.com/auth/analytics.readonly',
            ]),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_reporting.client_id'),
            'client_secret' => config('services.google_reporting.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            throw new RuntimeException('Google authorization code exchange failed.');
        }

        return $response->json();
    }

    public function storeRefreshToken(string $refreshToken): void
    {
        Setting::set('google_oauth_refresh_token', Crypt::encryptString($refreshToken));
        Cache::forget('google.marketing.access_token');
    }

    public function disconnect(): void
    {
        Setting::set('google_oauth_refresh_token', '');
        Cache::forget('google.marketing.access_token');
    }

    public function reports(string $startDate, string $endDate): array
    {
        $token = $this->accessToken();
        $searchConsole = null;
        $analytics = null;

        $siteProperty = trim((string) Setting::get('google_search_console_property', ''));
        if ($siteProperty !== '') {
            $url = 'https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($siteProperty).'/searchAnalytics/query';
            $searchConsole = Http::withToken($token)->acceptJson()->timeout(20)->post($url, [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['query'],
                'rowLimit' => 10,
                'dataState' => 'final',
            ])->throw()->json();
        }

        $propertyId = trim((string) Setting::get('google_analytics_property_id', ''));
        if ($propertyId !== '') {
            $analytics = Http::withToken($token)->acceptJson()->timeout(20)
                ->post('https://analyticsdata.googleapis.com/v1beta/properties/'.$propertyId.':runReport', [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                    'metrics' => [
                        ['name' => 'totalUsers'],
                        ['name' => 'sessions'],
                        ['name' => 'eventCount'],
                        ['name' => 'totalRevenue'],
                    ],
                ])->throw()->json();
        }

        return [
            'search_console' => $searchConsole,
            'analytics' => $analytics,
        ];
    }

    private function accessToken(): string
    {
        return Cache::remember('google.marketing.access_token', now()->addMinutes(50), function () {
            $encrypted = Setting::get('google_oauth_refresh_token');
            if (! is_string($encrypted) || $encrypted === '') {
                throw new RuntimeException('Google reporting is not connected.');
            }

            $refreshToken = Crypt::decryptString($encrypted);
            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google_reporting.client_id'),
                'client_secret' => config('services.google_reporting.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if (! $response->successful() || ! filled($response->json('access_token'))) {
                throw new RuntimeException('Google access token refresh failed.');
            }

            return (string) $response->json('access_token');
        });
    }
}
