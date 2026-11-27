<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAuditExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_download_the_seo_content_audit_as_utf8_csv(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.seo.index', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('cache-control', 'no-store, private');
        $this->assertStringStartsWith('seo-content-audit-', $response->headers->get('content-disposition') ? basename(str_replace(['attachment; filename=', '"'], '', $response->headers->get('content-disposition'))) : '');
    }
}
