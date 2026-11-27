<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAnalyticsSearchConsoleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Config::set('services.google_reporting.client_id', 'test-client-id');
        Config::set('services.google_reporting.client_secret', 'test-client-secret');
        Config::set('services.google_reporting.redirect', 'https://alomsenglishcenter.com/admin/seo/google/callback');
    }

    public function test_admin_can_start_google_read_only_oauth_connection(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.seo.google.connect'));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $response->headers->get('Location'));
        $this->assertStringContainsString('webmasters.readonly', $response->headers->get('Location'));
        $this->assertStringContainsString('analytics.readonly', $response->headers->get('Location'));
        $this->assertSessionHas('google_oauth_state');
    }

    public function test_oauth_callback_rejects_invalid_state(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->withSession(['google_oauth_state' => 'expected-state'])
            ->get(route('admin.seo.google.callback', ['state' => 'wrong-state', 'code' => 'auth-code']))
            ->assertForbidden();
    }

    public function test_oauth_callback_encrypts_refresh_token_before_storage(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token-secret',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $this->actingAs($admin)->withSession(['google_oauth_state' => 'secure-state'])
            ->get(route('admin.seo.google.callback', ['state' => 'secure-state', 'code' => 'auth-code']))
            ->assertRedirect(route('admin.seo.google.index'))
            ->assertSessionHas('success');

        $stored = Setting::get('google_oauth_refresh_token');
        $this->assertNotSame('refresh-token-secret', $stored);
        $this->assertSame('refresh-token-secret', Crypt::decryptString($stored));
    }

    public function test_google_report_page_displays_live_search_console_and_ga4_api_data(): void
    {
        Setting::set('google_oauth_refresh_token', Crypt::encryptString('refresh-token-secret'));
        Setting::set('google_search_console_property', 'https://alomsenglishcenter.com/');
        Setting::set('google_analytics_property_id', '123456789');
        Cache::forget('google.marketing.access_token');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'live-access-token',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/webmasters/v3/sites/*' => Http::response([
                'rows' => [[
                    'keys' => ['english course'],
                    'clicks' => 12,
                    'impressions' => 100,
                    'ctr' => 0.12,
                    'position' => 3.4,
                ]],
            ], 200),
            'https://analyticsdata.googleapis.com/v1beta/properties/*' => Http::response([
                'metricHeaders' => [
                    ['name' => 'totalUsers'],
                    ['name' => 'sessions'],
                    ['name' => 'eventCount'],
                    ['name' => 'totalRevenue'],
                ],
                'rows' => [[
                    'metricValues' => [
                        ['value' => '123'],
                        ['value' => '456'],
                        ['value' => '789'],
                        ['value' => '10.50'],
                    ],
                ]],
            ], 200),
        ]);

        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.seo.google.index'))
            ->assertOk()
            ->assertSee('Connected')
            ->assertSee('english course')
            ->assertSee('123')
            ->assertSee('456')
            ->assertSee('10.50')
            ->assertSee('12.00%');
    }

    public function test_search_console_property_rejects_non_https_and_unsafe_values(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/seo')->post(route('admin.seo.update'), [
            'google_search_console_property' => 'https://evil.example/path?redirect=https://attacker.test',
            'google_analytics_property_id' => '123456789',
        ])->assertSessionHasErrors('google_search_console_property');

        $this->assertNull(Setting::get('google_search_console_property'));
    }

    public function test_non_admin_cannot_open_google_reporting_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.seo.google.index'))->assertForbidden();
    }
}
