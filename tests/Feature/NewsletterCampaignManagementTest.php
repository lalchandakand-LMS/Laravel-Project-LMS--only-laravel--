<?php

namespace Tests\Feature;

use App\Jobs\DispatchNewsletterCampaign;
use App\Jobs\SendNewsletterCampaignEmail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignDelivery;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NewsletterCampaignManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_queue_a_campaign_for_confirmed_subscribers(): void
    {
        Queue::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.marketing.newsletter.campaigns.store'), [
            'subject' => 'November learning updates',
            'title' => 'What is new this month',
            'body' => 'New courses and practice sessions are now available.',
            'confirm_send' => '1',
        ])->assertRedirect(route('admin.marketing.newsletter.campaigns'))
            ->assertSessionHas('success');

        $campaign = NewsletterCampaign::firstOrFail();
        $this->assertSame('queued', $campaign->status);
        Queue::assertPushed(DispatchNewsletterCampaign::class, fn ($job) => $job->campaignId === $campaign->id);
    }

    public function test_non_admin_cannot_create_newsletter_campaigns(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.marketing.newsletter.campaigns'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.marketing.newsletter.campaigns.store'), [
            'subject' => 'Test',
            'title' => 'Test',
            'body' => 'Test body',
            'confirm_send' => '1',
        ])->assertForbidden();
    }

    public function test_dispatch_job_snapshots_only_confirmed_subscribers(): void
    {
        Queue::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $campaign = NewsletterCampaign::create([
            'created_by' => $admin->id,
            'subject' => 'Campaign',
            'title' => 'Campaign title',
            'body' => 'Campaign body',
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        NewsletterSubscriber::create(['email' => 'confirmed@example.test', 'status' => 'subscribed', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'pending@example.test', 'status' => 'pending']);
        NewsletterSubscriber::create(['email' => 'unsubscribed@example.test', 'status' => 'unsubscribed']);

        (new DispatchNewsletterCampaign($campaign->id))->handle();

        $this->assertDatabaseCount('newsletter_campaign_deliveries', 1);
        $this->assertDatabaseHas('newsletter_campaigns', [
            'id' => $campaign->id,
            'status' => 'sending',
            'recipients_count' => 1,
        ]);
        Queue::assertPushed(SendNewsletterCampaignEmail::class, 1);
    }

    public function test_delivery_job_sends_to_subscriber_and_updates_campaign_counts(): void
    {
        Mail::shouldReceive('raw')->once()->withArgs(function ($body, $callback) {
            return str_contains($body, 'Unsubscribe:')
                && is_callable($callback);
        });

        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $subscriber = NewsletterSubscriber::create([
            'email' => 'confirmed@example.test',
            'status' => 'subscribed',
            'confirmed_at' => now(),
        ]);
        $campaign = NewsletterCampaign::create([
            'created_by' => $admin->id,
            'subject' => 'Campaign',
            'title' => 'Campaign title',
            'body' => 'Campaign body',
            'status' => 'sending',
            'recipients_count' => 1,
        ]);
        $delivery = NewsletterCampaignDelivery::create([
            'newsletter_campaign_id' => $campaign->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'status' => 'pending',
        ]);

        (new SendNewsletterCampaignEmail($delivery->id))->handle();

        $this->assertDatabaseHas('newsletter_campaign_deliveries', [
            'id' => $delivery->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('newsletter_campaigns', [
            'id' => $campaign->id,
            'status' => 'sent',
            'sent_count' => 1,
            'failed_count' => 0,
        ]);
    }

    public function test_delivery_job_skips_subscribers_who_unsubscribe_before_send(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $subscriber = NewsletterSubscriber::create([
            'email' => 'left@example.test',
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
        $campaign = NewsletterCampaign::create([
            'created_by' => $admin->id,
            'subject' => 'Campaign',
            'title' => 'Campaign title',
            'body' => 'Campaign body',
            'status' => 'sending',
            'recipients_count' => 1,
        ]);
        $delivery = NewsletterCampaignDelivery::create([
            'newsletter_campaign_id' => $campaign->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'status' => 'pending',
        ]);

        (new SendNewsletterCampaignEmail($delivery->id))->handle();

        $this->assertDatabaseHas('newsletter_campaign_deliveries', [
            'id' => $delivery->id,
            'status' => 'skipped',
        ]);
        $this->assertDatabaseHas('newsletter_campaigns', [
            'id' => $campaign->id,
            'status' => 'sent',
            'skipped_count' => 1,
        ]);
    }
}
