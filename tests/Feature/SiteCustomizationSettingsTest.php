<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteCustomizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_save_homepage_footer_and_developer_customization(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'site_name' => "Alom's English Center",
            'homepage_learning_title' => 'Learning paths',
            'homepage_testimonial_title' => 'Student stories',
            'homepage_learning_bg' => '#ffffff',
            'homepage_learning_text' => '#0f172a',
            'homepage_testimonial_bg' => '#f1f5f9',
            'homepage_testimonial_text' => '#0f172a',
            'homepage_stats_bg' => '#047857',
            'homepage_stats_text' => '#ffffff',
            'footer_bg' => '#ffffff',
            'footer_text' => '#475569',
            'footer_heading' => '#0f172a',
            'footer_copyright' => 'All rights reserved.',
            'developer_name' => 'Example Studio',
            'developer_url' => 'https://example.com',
            'developer_text' => 'Developed by',
            'developer_bg' => '#0f172a',
            'developer_text_color' => '#ffffff',
            'developer_show' => '1',
        ])->assertRedirect();

        $this->assertSame('Learning paths', Setting::get('homepage_learning_title'));
        $this->assertSame('Student stories', Setting::get('homepage_testimonial_title'));
        $this->assertSame('Example Studio', Setting::get('developer_name'));
        $this->assertSame('1', Setting::get('developer_show'));
    }

    public function test_invalid_customization_color_is_rejected(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/settings')->post(route('admin.settings.update'), [
            'homepage_learning_bg' => 'red; background:url(javascript:alert(1))',
        ])->assertSessionHasErrors('homepage_learning_bg');
    }

    public function test_site_settings_page_exposes_separate_customization_tabs(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('General & Branding', false)
            ->assertSee('Menu Settings')
            ->assertSee('Slider Settings')
            ->assertSee('Homepage, footer & developer design', false);
    }
}
