<?php

namespace Tests\\Feature;

use App\\Models\\MarketingLead;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class MarketingLeadExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_export_filtered_marketing_leads_as_utf8_csv(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        MarketingLead::create([
            'name' => 'Bangla Course Prospect',
            'email' => 'prospect@example.test',
            'phone' => '+8801700000000',
            'message' => 'Interested in admission',
            'source' => 'facebook',
            'utm_source' => 'facebook',
            'utm_campaign' => 'winter-2026',
            'status' => 'new',
        ]);
        MarketingLead::create([
            'name' => 'Other Prospect',
            'email' => 'other@example.test',
            'message' => 'General inquiry',
            'source' => 'website',
            'status' => 'new',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.marketing.leads.index', [
            'campaign' => 'winter-2026',
            'export' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('cache-control', 'no-store, private');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('prospect@example.test', $csv);
        $this->assertStringContainsString('winter-2026', $csv);
        $this->assertStringNotContainsString('other@example.test', $csv);
    }

    public function test_non_admin_cannot_export_marketing_leads(): void
    {
        $student = User::where('email', 'student@lms.test')->firstOrFail();

        $this->actingAs($student)
            ->get(route('admin.marketing.leads.index', ['export' => 'csv']))
            ->assertForbidden();
    }
}
