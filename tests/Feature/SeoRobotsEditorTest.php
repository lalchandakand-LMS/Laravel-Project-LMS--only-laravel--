<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoRobotsEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_safe_custom_robots_rules_and_the_public_file_includes_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.seo.update'), [
            'seo_robots_index' => '1',
            'seo_robots_custom_rules' => "Disallow: /search\nAllow: /public-catalogue",
        ])->assertRedirect(route('admin.seo.index'));

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /search', false)
            ->assertSee('Allow: /public-catalogue', false)
            ->assertSee('Sitemap: '.route('sitemap'), false)
            ->assertSee('Disallow: /admin', false);
    }

    public function test_custom_robots_rules_cannot_override_private_paths_or_site_wide_indexing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.seo.index'))->post(route('admin.seo.update'), [
            'seo_robots_index' => '1',
            'seo_robots_custom_rules' => "Allow: /admin",
        ])->assertSessionHasErrors('seo_robots_custom_rules');

        Setting::set('seo_robots_index', '0');
        Setting::set('seo_robots_custom_rules', 'Disallow: /search');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false)
            ->assertDontSee('Sitemap:', false)
            ->assertDontSee('Disallow: /search', false);
    }
}
