<?php

namespace Tests\\Feature;

use App\\Models\\MarketingLead;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class MarketingLeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_contact_form_saves_lead_with_session_utm_attribution(): void
    {
        $this->withSession(['marketing_attribution' => [
            'utm_source' => 'facebook',
            'utm_medium' => 'paid_social',
            'utm_campaign' => 'spoken-english-november',
            'utm_term' => 'spoken english',
            'utm_content' => 'video-ad-a',
            'landing_page' => '/courses/spoken-english',
            'referrer_host' => 'facebook.com',
        ]])->post(route('contact.send'), [
            'name' => 'Amina Rahman',
            'email' => 'amina@example.test',
            'phone' => '01700000000',
            'subject' => 'Spoken English admission',
            'message' => 'Please send course details.',
        ])->assertRedirect();

        $this->assertDatabaseHas('marketing_leads', [
            'name' => 'Amina Rahman',
            'email' => 'amina@example.test',
            'phone' => '01700000000',
            'source' => 'facebook',
            'utm_medium' => 'paid_social',
            'utm_campaign' => 'spoken-english-november',
            'utm_term' => 'spoken english',
            'utm_content' => 'video-ad-a',
            'landing_page' => '/courses/spoken-english',
            'referrer_host' => 'facebook.com',
            'status' => 'new',
        ]);
    }

    public function test_contact_honeypot_does_not_create_a_lead(): void
    {
        $this->post(route('contact.send'), [
            'name' => 'Spam Bot',
            'email' => 'spam@example.test',
            'message' => 'Automated submission',
            'website' => 'https://spam.example.test',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('marketing_leads', 0);
    }

    public function test_admin_can_review_filter_and_update_marketing_leads(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $lead = MarketingLead::create([
            'name' => 'Amina Rahman',
            'email' => 'amina@example.test',
            'message' => 'Interested in IELTS.',
            'utm_campaign' => 'ielts-winter',
        ]);

        $this->actingAs($admin)->get(route('admin.marketing.leads.index', ['campaign' => 'ielts-winter']))
            ->assertOk()->assertSee('Amina Rahman')->assertSee('ielts-winter');

        $this->actingAs($admin)->patch(route('admin.marketing.leads.update', $lead), [
            'status' => 'converted',
            'admin_notes' => 'Called and completed admission.',
        ])->assertRedirect(route('admin.marketing.leads.index'));

        $this->assertDatabaseHas('marketing_leads', [
            'id' => $lead->id,
            'status' => 'converted',
            'admin_notes' => 'Called and completed admission.',
        ]);
        $this->assertNotNull($lead->fresh()->converted_at);
    }

    public function test_non_admin_cannot_access_or_update_marketing_leads(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $lead = MarketingLead::create([
            'name' => 'Amina Rahman',
            'email' => 'amina@example.test',
            'message' => 'Interested in IELTS.',
        ]);

        $this->actingAs($student)->get(route('admin.marketing.leads.index'))->assertForbidden();
        $this->actingAs($student)->patch(route('admin.marketing.leads.update', $lead), [
            'status' => 'spam',
        ])->assertForbidden();
    }

    public function test_invalid_lead_status_is_rejected(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $lead = MarketingLead::create([
            'name' => 'Amina Rahman',
            'email' => 'amina@example.test',
            'message' => 'Interested in IELTS.',
        ]);

        $this->actingAs($admin)->from('/admin/marketing/leads')->patch(route('admin.marketing.leads.update', $lead), [
            'status' => 'arbitrary',
        ])->assertSessionHasErrors('status');

        $this->assertSame('new', $lead->fresh()->status);
    }
}
