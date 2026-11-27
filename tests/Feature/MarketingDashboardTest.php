<?php

namespace Tests\Feature;

use App\Models\MarketingCampaign;
use App\Models\MarketingLead;
use App\Models\Order;
use App\Models\Payment;
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

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'partially_paid',
            'utm_campaign' => $campaign->slug,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bank',
            'amount' => 400,
            'transaction_id' => 'MARKETING-DASHBOARD-REVENUE',
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.marketing.dashboard'))
            ->assertOk()
            ->assertSee('Marketing Performance Dashboard')
            ->assertSee('Total captured leads')
            ->assertSee(number_format($initialTotal + 3))
            ->assertSee(number_format((($initialConverted + 1) / ($initialTotal + 3)) * 100, 1).'%')
            ->assertSee('IELTS Facebook Campaign')
            ->assertSee('2 leads')
            ->assertSee('Attributed orders')
            ->assertSee('৳400.00')
            ->assertSee('facebook')
            ->assertSee('google');
    }

    public function test_dashboard_date_filter_limits_lead_metrics_and_rejects_long_ranges(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        MarketingLead::create([
            'name' => 'Recent lead',
            'email' => 'recent-period@example.test',
            'message' => 'Recent',
            'status' => 'new',
            'created_at' => now()->subDays(3),
        ]);
        MarketingLead::create([
            'name' => 'Old lead',
            'email' => 'old-period@example.test',
            'message' => 'Old',
            'status' => 'new',
            'created_at' => now()->subDays(40),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.marketing.dashboard', [
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Recent lead')
            ->assertSee('Total captured leads');

        $this->actingAs($admin)
            ->get(route('admin.marketing.dashboard', [
                'from' => now()->subDays(45)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertSessionHasErrors('to');
    }

    public function test_non_admin_cannot_view_marketing_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.marketing.dashboard'))->assertForbidden();
    }
}
