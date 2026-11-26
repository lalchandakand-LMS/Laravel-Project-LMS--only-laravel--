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
            'site_primary_color' => '#ea580c',
            'site_secondary_color' => '#9a3412',
            'homepage_learning_title' => 'Learning paths',
            'homepage_testimonial_title' => 'Student stories',
            'homepage_learning_layout' => 'grid',
            'homepage_featured_layout' => 'carousel',
            'homepage_carousel_autoplay' => '1',
            'homepage_carousel_speed' => '8',
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
            'brand_loader_enabled' => '1',
            'brand_loader_message' => 'Loading Alom LMS',
            'brand_loader_style' => 'dots',
            'brand_loader_min_time' => '400',
            'brand_loader_bg' => '#ffffff',
            'brand_loader_color' => '#047857',
            'brand_loader_text_color' => '#0f172a',
        ])->assertRedirect();

        $this->assertSame('#ea580c', Setting::get('site_primary_color'));
        $this->assertSame('#9a3412', Setting::get('site_secondary_color'));
        $this->assertSame('Learning paths', Setting::get('homepage_learning_title'));
        $this->assertSame('Student stories', Setting::get('homepage_testimonial_title'));
        $this->assertSame('grid', Setting::get('homepage_learning_layout'));
        $this->assertSame('carousel', Setting::get('homepage_featured_layout'));
        $this->assertSame('1', Setting::get('homepage_carousel_autoplay'));
        $this->assertSame('8', Setting::get('homepage_carousel_speed'));
        $this->assertSame('Example Studio', Setting::get('developer_name'));
        $this->assertSame('1', Setting::get('developer_show'));
        $this->assertSame('1', Setting::get('brand_loader_enabled'));
        $this->assertSame('dots', Setting::get('brand_loader_style'));
        $this->assertSame('Loading Alom LMS', Setting::get('brand_loader_message'));
    }


    public function test_homepage_sections_render_saved_carousel_preferences(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'homepage_show_categories' => '1',
            'homepage_learning_layout' => 'carousel',
            'homepage_featured_layout' => 'grid',
            'homepage_carousel_autoplay' => '1',
            'homepage_carousel_speed' => '9',
        ])->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('data-home-carousel="categories"', false)
            ->assertSee('data-home-carousel-layout="carousel"', false)
            ->assertSee('data-home-carousel-autoplay="1"', false)
            ->assertSee('data-home-carousel-speed="9"', false)
            ->assertSee('data-home-carousel="categories"', false);
    }


    public function test_admin_can_control_reference_homepage_sections(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'homepage_show_hero' => '1',
            'homepage_show_categories' => '1',
            'homepage_show_featured' => '1',
            'homepage_show_online_batches' => '0',
            'homepage_show_stats' => '1',
            'homepage_show_latest' => '1',
            'homepage_show_reviews' => '1',
            'homepage_show_ebooks' => '1',
        ])->assertRedirect();

        $this->assertSame('0', Setting::get('homepage_show_online_batches'));
        $this->assertSame('1', Setting::get('homepage_show_categories'));

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Hero slider / banner')
            ->assertSee('Online batches')
            ->assertSee('Trust statistics');
    }

    public function test_invalid_homepage_carousel_preferences_are_rejected(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/settings')->post(route('admin.settings.update'), [
            'homepage_learning_layout' => 'ticker',
            'homepage_carousel_speed' => '99',
        ])->assertSessionHasErrors(['homepage_learning_layout', 'homepage_carousel_speed']);
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
            ->assertSee('Homepage, footer & developer design', false)
            ->assertSee('Brand Loading');
    }

    public function test_invalid_public_brand_colors_are_rejected(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/settings')->post(route('admin.settings.update'), [
            'site_primary_color' => 'orange; background:url(javascript:alert(1))',
            'site_secondary_color' => '#12345',
        ])->assertSessionHasErrors(['site_primary_color', 'site_secondary_color']);
    }

    public function test_public_brand_palette_is_rendered_on_the_website(): void
    {
        Setting::set('site_primary_color', '#ea580c');
        Setting::set('site_secondary_color', '#9a3412');

        $this->get('/')
            ->assertOk()
            ->assertSee('--aec-primary: #ea580c', false)
            ->assertSee('--aec-primary-dark: #9a3412', false);
    }

    public function test_admin_can_save_and_render_homepage_section_order(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $order = ['hero', 'featured', 'categories', 'online_batches', 'stats', 'latest', 'live_classes', 'exams', 'teachers', 'board_results', 'reviews', 'ebooks', 'admission'];

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'homepage_section_order' => json_encode($order),
        ])->assertRedirect();

        $this->assertSame($order, json_decode(Setting::get('homepage_section_order'), true));

        $this->get('/')
            ->assertOk()
            ->assertSee('const savedOrder = ["hero","featured","categories"', false)
            ->assertSee('data-home-section="categories"', false)
            ->assertSee('data-home-section="featured"', false);
    }

    public function test_invalid_homepage_section_order_is_rejected(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from('/admin/settings')->post(route('admin.settings.update'), [
            'homepage_section_order' => json_encode(['hero', 'hero']),
        ])->assertSessionHasErrors('homepage_section_order');
    }
}

