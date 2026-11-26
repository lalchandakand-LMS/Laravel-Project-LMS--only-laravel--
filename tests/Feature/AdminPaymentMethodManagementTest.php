<?php

namespace Tests\\Feature;

use App\\Models\\Setting;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\Crypt;
use Tests\\TestCase;

class AdminPaymentMethodManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_global_payment_methods_and_gateway_credentials_are_encrypted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.payment-methods.index'))
            ->assertOk()
            ->assertSee('Global payment methods')
            ->assertSee('bKash API configuration')
            ->assertSee('Sandbox / Test');

        $this->actingAs($admin)->post(route('admin.payment-methods.update'), [
            'methods' => ['bkash', 'nagad', 'bank'],
            'providers' => [
                'bkash' => [
                    'mode' => 'sandbox',
                    'credentials' => [
                        'merchant_number' => '01712345678',
                        'app_key' => 'test-app-key',
                        'app_secret' => 'test-app-secret',
                        'username' => 'test-user',
                        'password' => 'test-password',
                        'base_url' => 'https://sandbox.example.test',
                        'webhook_secret' => 'test-webhook-secret',
                    ],
                ],
                'nagad' => ['mode' => 'sandbox', 'credentials' => ['merchant_id' => 'NAGAD-MERCHANT-1']],
                'rocket' => ['mode' => 'sandbox', 'credentials' => []],
            ],
        ])->assertRedirect(route('admin.payment-methods.index'));

        $this->assertSame(
            ['bkash', 'nagad', 'bank'],
            json_decode(Setting::get('payment_methods.global_enabled'), true)
        );

        $stored = Setting::get('payment_gateway_credentials.bkash');
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('test-app-secret', $stored);
        $this->assertStringNotContainsString('test-password', $stored);

        $payload = json_decode(Crypt::decryptString($stored), true);
        $this->assertSame('sandbox', $payload['mode']);
        $this->assertSame('test-app-secret', $payload['credentials']['app_secret']);

        $this->actingAs($admin)->get(route('admin.payment-methods.index'))
            ->assertOk()
            ->assertSee('Credentials saved')
            ->assertDontSee('test-app-secret');
    }

    public function test_admin_can_clear_saved_gateway_credentials_and_disable_all_methods(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Setting::set('payment_gateway_credentials.nagad', Crypt::encryptString(json_encode([
            'mode' => 'sandbox',
            'credentials' => ['merchant_id' => 'OLD-MERCHANT'],
        ])));

        $this->actingAs($admin)->post(route('admin.payment-methods.update'), [
            'methods' => [],
            'providers' => [
                'nagad' => [
                    'mode' => 'sandbox',
                    'clear_credentials' => '1',
                    'credentials' => [],
                ],
            ],
        ])->assertRedirect(route('admin.payment-methods.index'));

        $this->assertSame([], json_decode(Setting::get('payment_methods.global_enabled'), true));
        $payload = json_decode(Crypt::decryptString(Setting::get('payment_gateway_credentials.nagad')), true);
        $this->assertSame([], $payload['credentials']);
    }

    public function test_non_admin_cannot_manage_payment_methods(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.payment-methods.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.payment-methods.update'), [])->assertForbidden();
    }
}
