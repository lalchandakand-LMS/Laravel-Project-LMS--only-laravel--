<?php

namespace Tests\\Feature;

use App\\Models\\Branch;
use App\\Models\\Course;
use App\\Models\\MarketingCampaign;
use App\\Models\\Order;
use App\\Models\\Payment;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class MarketingOrderAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_checkout_persists_the_visitors_utm_campaign_on_the_order(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::create([
            'title' => 'Campaign Attribution Test Course',
            'slug' => 'campaign-attribution-test-course',
            'description' => 'Test course for UTM order attribution.',
            'price' => 500,
            'is_published' => true,
            'instructor_id' => $admin->id,
        ]);
        $branch = Branch::create([
            'name' => 'Attribution Test Branch',
            'code' => 'ATTR-TEST',
            'is_active' => true,
            'payment_methods' => ['bank'],
        ]);

        $this->actingAs($student)->withSession([
            'cart' => ['course:'.$course->id],
            'marketing_attribution' => [
                'utm_source' => 'facebook',
                'utm_medium' => 'paid_social',
                'utm_campaign' => 'spoken-english-november',
                'utm_term' => 'spoken english',
                'utm_content' => 'video-ad-a',
                'landing_page' => '/courses/spoken-english',
                'referrer_host' => 'facebook.com',
            ],
        ])->post(route('checkout.store'), [
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
            'branch_id' => $branch->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'user_id' => $student->id,
            'utm_source' => 'facebook',
            'utm_medium' => 'paid_social',
            'utm_campaign' => 'spoken-english-november',
            'utm_term' => 'spoken english',
            'utm_content' => 'video-ad-a',
            'landing_page' => '/courses/spoken-english',
            'referrer_host' => 'facebook.com',
        ]);
    }

    public function test_campaign_manager_reports_order_count_and_verified_revenue(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $campaign = MarketingCampaign::create([
            'created_by' => $admin->id,
            'name' => 'Revenue Attribution Campaign',
            'channel' => 'facebook',
            'source' => 'facebook',
            'medium' => 'paid_social',
            'destination_url' => '/courses',
            'status' => 'active',
        ]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 1500,
            'discount' => 0,
            'total' => 1500,
            'status' => 'partially_paid',
            'utm_campaign' => $campaign->slug,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bank',
            'amount' => 750,
            'transaction_id' => 'ATTRIBUTED-VERIFIED-1',
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.marketing.campaigns.index'))
            ->assertOk()
            ->assertSee('Revenue Attribution Campaign')
            ->assertSee('750.00')
            ->assertSee('Orders / Revenue');
    }
}
