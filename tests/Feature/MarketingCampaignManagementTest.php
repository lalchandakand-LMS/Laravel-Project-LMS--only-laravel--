<?php

namespace Tests\\Feature;

use App\\Models\\MarketingCampaign;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class MarketingCampaignManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_create_campaign_and_generate_utm_tracking_url(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.marketing.campaigns.store'), [
            'name' => 'Spoken English November',
            'channel' => 'facebook',
            'source' => 'facebook',
            'medium' => 'paid_social',
            'term' => 'spoken english',
            'content' => 'video ad 1',
            'destination_url' => '/courses',
            'budget' => '2500.00',
            'status' => 'active',
        ])->assertRedirect(route('admin.marketing.campaigns.index'));

        $campaign = MarketingCampaign::where('name', 'Spoken English November')->firstOrFail();
        $this->assertSame('spoken-english-november', $campaign->slug);
        $this->assertStringContainsString('utm_source=facebook', $campaign->trackingUrl());
        $this->assertStringContainsString('utm_medium=paid_social', $campaign->trackingUrl());
        $this->assertStringContainsString('utm_campaign=spoken-english-november', $campaign->trackingUrl());
        $this->assertStringContainsString('utm_term=spoken+english', $campaign->trackingUrl());
    }

    public function test_campaign_rejects_unsafe_destination_urls(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/marketing/campaigns/create')->post(route('admin.marketing.campaigns.store'), [
            'name' => 'Unsafe',
            'channel' => 'other',
            'source' => 'test',
            'medium' => 'test',
            'destination_url' => '//evil.example/path',
            'status' => 'draft',
        ])->assertSessionHasErrors('destination_url');

        $this->assertDatabaseCount('marketing_campaigns', 0);
    }

    public function test_admin_can_edit_and_archive_campaign_without_deleting_lead_attribution(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $campaign = MarketingCampaign::create([
            'created_by' => $admin->id,
            'name' => 'IELTS Winter',
            'channel' => 'google',
            'source' => 'google',
            'medium' => 'cpc',
            'destination_url' => '/courses',
            'status' => 'draft',
        ]);
        $lead = \\App\\Models\\MarketingLead::create([
            'name' => 'Amina',
            'email' => 'amina@example.test',
            'message' => 'Interested',
            'utm_campaign' => $campaign->slug,
        ]);

        $this->actingAs($admin)->put(route('admin.marketing.campaigns.update', $campaign), [
            'name' => 'IELTS Winter Updated',
            'channel' => 'google',
            'source' => 'google',
            'medium' => 'cpc',
            'destination_url' => '/courses',
            'status' => 'paused',
        ])->assertRedirect(route('admin.marketing.campaigns.index'));

        $this->actingAs($admin)->delete(route('admin.marketing.campaigns.destroy', $campaign))
            ->assertRedirect(route('admin.marketing.campaigns.index'));

        $this->assertSoftDeleted('marketing_campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseHas('marketing_leads', ['id' => $lead->id, 'utm_campaign' => $campaign->slug]);
    }

    public function test_non_admin_cannot_manage_campaigns(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.marketing.campaigns.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.marketing.campaigns.store'), [])->assertForbidden();
    }
}
