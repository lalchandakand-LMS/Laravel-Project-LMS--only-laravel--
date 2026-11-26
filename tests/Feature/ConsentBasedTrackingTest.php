<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentBasedTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_save_valid_ga4_and_meta_pixel_ids(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.seo.update'), [
            'ga_measurement_id' => 'G-ABC1234567',
            'meta_pixel_id' => '123456789012345',
            'seo_robots_index' => '1',
        ])->assertRedirect(route('admin.seo.index'))->assertSessionHasNoErrors();

        $this->assertSame('G-ABC1234567', Setting::get('ga_measurement_id'));
        $this->assertSame('123456789012345', Setting::get('meta_pixel_id'));
    }

    public function test_invalid_tracking_ids_are_rejected_and_not_saved(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/seo')->post(route('admin.seo.update'), [
            'ga_measurement_id' => 'javascript:alert(1)',
            'meta_pixel_id' => '<script>alert(1)</script>',
        ])->assertSessionHasErrors(['ga_measurement_id', 'meta_pixel_id']);

        $this->assertNull(Setting::get('ga_measurement_id'));
        $this->assertNull(Setting::get('meta_pixel_id'));
    }

    public function test_public_pages_show_optional_consent_before_loading_configured_trackers(): void
    {
        Setting::set('ga_measurement_id', 'G-ABC1234567');
        Setting::set('meta_pixel_id', '123456789012345');

        $this->get('/')->assertOk()
            ->assertSee('Your privacy choices')
            ->assertSee('Accept all')
            ->assertSee('Reject optional')
            ->assertSee('Customize')
            ->assertSee('data-ga-id="G-ABC1234567"', false)
            ->assertSee('data-meta-pixel-id="123456789012345"', false)
            ->assertSee("document.createElement('script')", false)
            ->assertDontSee('<script async src="https://www.googletagmanager.com/gtag/js', false);
    }

    public function test_privacy_policy_explains_optional_tracking_and_withdrawal(): void
    {
        Setting::set('ga_measurement_id', 'G-ABC1234567');

        $this->get('/privacy-policy')->assertOk()
            ->assertSee('Optional cookies and analytics')
            ->assertSee('loaded only after you choose')
            ->assertSee('withdraw optional consent');
    }

    public function test_paid_checkout_confirmation_emits_purchase_event_only_with_consent_manager_available(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Setting::set('ga_measurement_id', 'G-ABC1234567');
        Setting::set('meta_pixel_id', '123456789012345');

        $order = Order::create([
            'user_id' => $user->id,
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'status' => 'paid',
            'name' => 'Test Student',
            'phone' => '01700000000',
            'email' => 'student@example.test',
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bank',
            'amount' => 500,
            'transaction_id' => 'CONSENT-PURCHASE-1',
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('checkout.success', $order->order_no))->assertOk()
            ->assertSee('data-disable-pageview="1"', false)
            ->assertSee('aecPurchaseEvent')
            ->assertSee('CONSENT-PURCHASE-1', false)
            ->assertSee('Cookie settings');
    }

    public function test_pending_checkout_confirmation_does_not_emit_purchase_event(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $order = Order::create([
            'user_id' => $user->id,
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'status' => 'pending',
            'name' => 'Test Student',
            'phone' => '01700000000',
            'email' => 'student@example.test',
        ]);

        $this->actingAs($user)->get(route('checkout.success', $order->order_no))->assertOk()
            ->assertDontSee('aecPurchaseEvent');
    }

    public function test_authenticated_workspace_does_not_load_public_tracking_consent_or_scripts(): void
    {
        Setting::set('ga_measurement_id', 'G-ABC1234567');
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()
            ->assertDontSee('aec-cookie-consent')
            ->assertDontSee('googletagmanager.com/gtag/js');
    }
}
