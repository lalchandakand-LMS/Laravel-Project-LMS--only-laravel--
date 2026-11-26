<?php

namespace Tests\\Feature;

use App\\Models\\MarketingPopup;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class MarketingPopupBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_create_and_archive_a_scheduled_popup(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.marketing.popups.store'), [
            'title' => 'November Admission Offer',
            'body' => 'Apply this week for the next English batch.',
            'image_url' => '/storage/media/admission-offer.jpg',
            'cta_label' => 'Apply now',
            'cta_url' => '/admission',
            'target_audience' => 'all',
            'display_on' => 'homepage_only',
            'frequency' => 'once_per_session',
            'priority' => 50,
            'starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'status' => 'active',
        ])->assertRedirect(route('admin.marketing.popups.index'));

        $popup = MarketingPopup::where('title', 'November Admission Offer')->firstOrFail();
        $this->assertSame('active', $popup->status);
        $this->assertSame('homepage_only', $popup->display_on);

        $this->actingAs($admin)->delete(route('admin.marketing.popups.destroy', $popup))
            ->assertRedirect(route('admin.marketing.popups.index'));

        $this->assertSoftDeleted('marketing_popups', ['id' => $popup->id]);
    }

    public function test_popup_rejects_unsafe_image_and_cta_urls(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/marketing/popups/create')->post(route('admin.marketing.popups.store'), [
            'title' => 'Unsafe popup',
            'image_url' => 'javascript:alert(1)',
            'cta_label' => 'Click',
            'cta_url' => '//evil.example/path',
            'target_audience' => 'all',
            'display_on' => 'all_public_pages',
            'frequency' => 'once_per_session',
            'priority' => 0,
            'status' => 'draft',
        ])->assertSessionHasErrors(['image_url', 'cta_url']);

        $this->assertDatabaseCount('marketing_popups', 0);
    }

    public function test_active_popup_is_rendered_on_public_homepage(): void
    {
        MarketingPopup::create([
            'title' => 'Homepage Admission Offer',
            'body' => 'Register for the new batch.',
            'cta_label' => 'View courses',
            'cta_url' => '/courses',
            'target_audience' => 'all',
            'display_on' => 'homepage_only',
            'frequency' => 'once_per_session',
            'priority' => 10,
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
        ]);

        $this->get('/')->assertOk()
            ->assertSee('aec-marketing-popup')
            ->assertSee('Homepage Admission Offer')
            ->assertSee('Register for the new batch.');
    }

    public function test_expired_popup_is_not_rendered(): void
    {
        MarketingPopup::create([
            'title' => 'Expired Offer',
            'body' => 'This offer has expired.',
            'target_audience' => 'all',
            'display_on' => 'all_public_pages',
            'frequency' => 'once_per_session',
            'priority' => 10,
            'status' => 'active',
            'ends_at' => now()->subDay(),
        ]);

        $this->get('/')->assertOk()->assertDontSee('Expired Offer');
    }

    public function test_popup_metrics_require_analytics_consent(): void
    {
        $popup = MarketingPopup::create([
            'title' => 'Trackable Offer',
            'target_audience' => 'all',
            'display_on' => 'all_public_pages',
            'frequency' => 'once_per_session',
            'priority' => 10,
            'status' => 'active',
        ]);

        $this->post(route('marketing.popups.impression', $popup), [])
            ->assertSessionHasErrors('analytics_consent');
        $this->assertSame(0, $popup->fresh()->impressions);

        $this->post(route('marketing.popups.impression', $popup), ['analytics_consent' => true])->assertNoContent();
        $this->post(route('marketing.popups.click', $popup), ['analytics_consent' => true])->assertNoContent();

        $this->assertSame(1, $popup->fresh()->impressions);
        $this->assertSame(1, $popup->fresh()->clicks);
    }

    public function test_non_admin_cannot_manage_popups(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.marketing.popups.index'))->assertForbidden();
    }
}
