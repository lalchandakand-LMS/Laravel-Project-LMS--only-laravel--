<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\GoogleMarketingReporting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleAnalyticsSearchConsoleController extends Controller
{
    public function index(GoogleMarketingReporting $google)
    {
        $startDate = now()->subDays(28)->toDateString();
        $endDate = now()->subDay()->toDateString();
        $reports = ['search_console' => null, 'analytics' => null];
        $error = null;
        $hasProperty = filled(Setting::get('google_search_console_property'))
            || filled(Setting::get('google_analytics_property_id'));

        if ($google->connected() && $hasProperty) {
            try {
                $reports = $google->reports($startDate, $endDate);
            } catch (Throwable $exception) {
                report($exception);
                $error = 'Google could not return reports. Check that the authorized account has access to both configured properties and that the required APIs are enabled.';
            }
        }

        return view('admin.seo.google-report', [
            'credentialsConfigured' => $google->credentialsConfigured(),
            'connected' => $google->connected(),
            'siteProperty' => Setting::get('google_search_console_property', ''),
            'analyticsPropertyId' => Setting::get('google_analytics_property_id', ''),
            'reports' => $reports,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'error' => $error,
            'hasProperty' => $hasProperty,
        ]);
    }

    public function connect(Request $request, GoogleMarketingReporting $google)
    {
        if (! $google->credentialsConfigured()) {
            return redirect()->route('admin.seo.google.index')
                ->with('error', 'Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in the server environment first.');
        }

        $state = Str::random(64);
        $request->session()->put('google_oauth_state', $state);
        $redirectUri = config('services.google_reporting.redirect') ?: route('admin.seo.google.callback');

        return redirect()->away($google->authorizationUrl($state, $redirectUri));
    }

    public function callback(Request $request, GoogleMarketingReporting $google)
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        $providedState = (string) $request->query('state', '');

        abort_unless(
            is_string($expectedState) && $expectedState !== '' && hash_equals($expectedState, $providedState),
            403,
            'Invalid Google authorization state.'
        );

        if ($request->filled('error')) {
            return redirect()->route('admin.seo.google.index')
                ->with('error', 'Google authorization was not completed.');
        }

        $validated = $request->validate(['code' => ['required', 'string', 'max:2048']]);
        $redirectUri = config('services.google_reporting.redirect') ?: route('admin.seo.google.callback');

        try {
            $tokens = $google->exchangeCode($validated['code'], $redirectUri);
            $refreshToken = $tokens['refresh_token'] ?? null;

            if (filled($refreshToken)) {
                $google->storeRefreshToken((string) $refreshToken);
            } elseif (! $google->connected()) {
                return redirect()->route('admin.seo.google.index')
                    ->with('error', 'Google did not return an offline refresh token. Revoke this app in your Google Account and connect again with consent.');
            }
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.seo.google.index')
                ->with('error', 'Google connection failed. Check the OAuth client, redirect URI and enabled APIs.');
        }

        return redirect()->route('admin.seo.google.index')->with('success', 'Google Search Console and Analytics access connected.');
    }

    public function disconnect(GoogleMarketingReporting $google)
    {
        $google->disconnect();

        return redirect()->route('admin.seo.google.index')->with('success', 'Google reporting connection removed.');
    }
}
