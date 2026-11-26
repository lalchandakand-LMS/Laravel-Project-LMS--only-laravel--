<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_manage_global_seo_metadata_and_indexing_policy(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.seo.index'))
            ->assertOk()
            ->assertSee('Global SEO defaults')
            ->assertSee(route('sitemap'));

        $this->actingAs($admin)->post(route('admin.seo.update'), [
            'seo_title' => 'Learn English Online',
            'seo_description' => 'Practical English courses for learners in Bangladesh.',
            'seo_default_image' => 'https://example.test/share.jpg',
            'google_site_verification' => 'verification_token-123',
            'seo_robots_index' => '1',
        ])->assertRedirect(route('admin.seo.index'))->assertSessionHasNoErrors();

        $this->assertSame('Learn English Online', Setting::get('seo_title'));
        $this->assertSame('Practical English courses for learners in Bangladesh.', Setting::get('seo_description'));
        $this->assertSame('1', Setting::get('seo_robots_index'));

        auth()->logout();
        $this->get('/contact')->assertOk()
            ->assertSee('name="google-site-verification" content="verification_token-123"', false)
            ->assertSee('content="Practical English courses for learners in Bangladesh."', false);
    }

    public function test_invalid_seo_urls_and_verification_tokens_are_rejected(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/seo')->post(route('admin.seo.update'), [
            'seo_default_image' => 'javascript:alert(1)',
            'google_site_verification' => '<script>alert(1)</script>',
        ])->assertSessionHasErrors(['seo_default_image', 'google_site_verification']);

        $this->assertNull(Setting::get('seo_default_image'));
    }

    public function test_robots_txt_excludes_authenticated_workspaces_and_exposes_sitemap(): void
    {
        $this->get(route('robots'))->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /my/')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_disabling_public_indexing_blocks_site_crawling(): void
    {
        Setting::set('seo_robots_index', '0');

        $this->get(route('robots'))->assertOk()
            ->assertSee('Disallow: /')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/')->assertOk()->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_non_admin_users_cannot_access_seo_management(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.seo.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.seo.update'), [])->assertForbidden();
    }
}
