<?php

namespace Tests\Feature;

use App\Models\MarketingLead;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPopupLeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Setting::set('marketing_popup_enabled', '1');
    }

    public function test_popup_lead_capture_requires_consent_and_records_utm_attribution(): void
    {
        $this->withSession(['marketing_attribution' => [
            'utm_source' => 'facebook',
            'utm_medium' => 'paid_social',
            'utm_campaign' => 'spoken-english',
            'landing_page' => '/',
            'referrer_host' => 'facebook.com',
        ]])->post(route('marketing.popup.capture'), [
            'name' => 'Amina Rahman',
            'email' => 'Amina@Example.test',
            'phone' => '01700000000',
            'consent' => '1',
        ])->assertRedirect()->assertSessionHas('success')
            ->assertSessionHas('aec_lead_event', fn ($event) => isset($event['event_id']) && $event['method'] === 'marketing_popup');

        Setting::set('ga_measurement_id', 'G-ABC1234567');
        Setting::set('meta_pixel_id', '123456789012345');
        $this->get('/')->assertOk()
            ->assertSee('window.aecLeadEvent =', false)
            ->assertSee('generate_lead', false)
            ->assertSee("fbq('track', 'Lead'", false)
            ->assertDontSee('amina@example.test');

        $this->assertDatabaseHas('marketing_leads', [
            'name' => 'Amina Rahman',
            'email' => 'amina@example.test',
            'source' => 'marketing_popup',
            'utm_source' => 'facebook',
            'utm_medium' => 'paid_social',
            'utm_campaign' => 'spoken-english',
            'landing_page' => '/',
            'referrer_host' => 'facebook.com',
            'status' => 'new',
        ]);
    }

    public function test_popup_lead_capture_rejects_missing_consent(): void
    {
        $this->post(route('marketing.popup.capture'), [
            'name' => 'No Consent',
            'email' => 'no-consent@example.test',
        ])->assertSessionHasErrors('consent');

        $this->assertDatabaseCount('marketing_leads', 0);
    }

    public function test_disabled_popup_does_not_accept_submissions(): void
    {
        Setting::set('marketing_popup_enabled', '0');

        $this->post(route('marketing.popup.capture'), [
            'name' => 'Visitor',
            'email' => 'visitor@example.test',
            'consent' => '1',
        ])->assertNotFound();

        $this->assertDatabaseCount('marketing_leads', 0);
    }

    public function test_admin_can_manage_popup_but_student_cannot(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.marketing.popup.edit'))->assertOk();

        $this->actingAs($admin)->put(route('admin.marketing.popup.update'), [
            'enabled' => '1',
            'title' => 'Admission is open',
            'body' => 'Ask our team about the next batch.',
            'button_text' => 'Contact me',
            'delay' => '5',
            'frequency' => 'week',
            'display' => 'home',
            'destination_url' => '/courses',
        ])->assertRedirect(route('admin.marketing.popup.edit'));

        $this->assertSame('1', Setting::get('marketing_popup_enabled'));
        $this->assertSame('Admission is open', Setting::get('marketing_popup_title'));

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->get(route('admin.marketing.popup.edit'))->assertForbidden();
    }

    public function test_popup_rejects_external_redirect_destination(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/marketing/popup')->put(route('admin.marketing.popup.update'), [
            'enabled' => '1',
            'title' => 'Admission is open',
            'body' => 'Ask our team.',
            'button_text' => 'Contact me',
            'delay' => '5',
            'frequency' => 'week',
            'display' => 'home',
            'destination_url' => '//evil.example/path',
        ])->assertSessionHasErrors('destination_url');
    }
}
