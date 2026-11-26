<?php

namespace Tests\Feature;

use App\Models\MarketingCampaign;
use App\Models\MarketingLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_lead_and_campaign_performance_metrics(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $initialTotal = MarketingLead::count();
        $initialConverted = MarketingLead::where('status', 'converted')->count();
        $campaign = MarketingCampaign::create([
            'created_by' => $admin->id,
            'name' => 'IELTS Facebook Campaign',
            'slug' => 'ielts-facebook-campaign',
            'channel' => 'facebook',
            'source' => 'facebook',
            'medium' => 'paid_social',
            'destination_url' => '/courses',
            'status' => 'active',
        ]);

        MarketingLead::create([
            'name' => 'New lead',
            'email' => 'new@example.test',
            'message' => 'Interested',
            'utm_source' => 'facebook',
            'utm_campaign' => $campaign->slug,
            'status' => 'new',
        ]);
        MarketingLead::create([
            'name' => 'Converted lead',
            'email' => 'converted@example.test',
            'message' => 'Enrolled',
            'utm_source' => 'facebook',
            'utm_campaign' => $campaign->slug,
            'status' => 'converted',
            'converted_at' => now(),
        ]);
        MarketingLead::create([
            'name' => 'Organic lead',
            'email' => 'organic@example.test',
            'message' => 'Found search',
            'utm_source' => 'google',
            'status' => 'qualified',
        ]);

        $this->actingAs($admin)->get(route('admin.marketing.dashboard'))
            ->assertOk()
            ->assertSee('Marketing Performance Dashboard')
            ->assertSee('Total captured leads')
            ->assertSee(number_format($initialTotal + 3))
            ->assertSee(number_format((($initialConverted + 1) / ($initialTotal + 3)) * 100, 1).'%')
            ->assertSee('IELTS Facebook Campaign')
            ->assertSee('2 leads')
            ->assertSee('facebook')
            ->assertSee('google');
    }

    public function test_non_admin_cannot_view_marketing_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.marketing.dashboard'))->assertForbidden();
    }
}
