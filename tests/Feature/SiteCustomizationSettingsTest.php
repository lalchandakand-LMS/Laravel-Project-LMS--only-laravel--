<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\MediaAsset;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'homepage_featured_title' => 'Popular English courses',
            'homepage_latest_title' => 'Recently added courses',
            'homepage_latest_subtitle' => 'Fresh learning materials this week.',
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
        $this->assertSame('Popular English courses', Setting::get('homepage_featured_title'));
        $this->assertSame('Recently added courses', Setting::get('homepage_latest_title'));
        $this->assertSame('Fresh learning materials this week.', Setting::get('homepage_latest_subtitle'));
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


    public function test_failed_brand_logo_storage_reports_an_error_and_keeps_the_existing_logo(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        Setting::set('logo', 'brand/existing-logo.png');
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

        $this->actingAs($admin)->from('/admin/settings')->post(route('admin.settings.update'), [
            'logo' => UploadedFile::fake()->image('new-logo.png'),
        ])->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('logo');

        $this->assertSame('brand/existing-logo.png', Setting::get('logo'));
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


    public function test_learning_highlights_render_as_independent_sections_immediately_after_the_slider(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSeeInOrder([
            'data-home-section="hero"',
            'data-home-section="online_batches"',
            'data-home-section="stats"',
            'data-home-section="categories"',
        ], false);

        $this->assertStringNotContainsString('data-home-section="online_batches"', $this->extractSectionMarkup($response->getContent(), 'hero'));
    }

    private function extractSectionMarkup(string $html, string $section): string
    {
        $start = strpos($html, 'data-home-section="'.$section.'"');
        $this->assertNotFalse($start, "Missing homepage section: {$section}");

        $end = strpos($html, 'data-home-section="', $start + 1);

        return substr($html, $start, $end === false ? null : $end - $start);
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

    public function test_new_installation_uses_cyan_teal_public_brand_defaults(): void
    {
        Cache::forget('settings.all');
        $this->get('/')
            ->assertOk()
            ->assertSee('--aec-primary: #06b6d4', false)
            ->assertSee('--aec-primary-dark: #0d9488', false)
            ->assertSee('linear-gradient(125deg,#062c30 0%,#041c24 100%)', false);
    }

    public function test_dark_homepage_theme_preserves_admin_section_colors_and_keeps_inner_cards_readable(): void
    {
        Setting::set('homepage_theme_mode', 'dark');
        Setting::set('homepage_online_batches_bg', '#123456');
        Setting::set('homepage_online_batches_text', '#fefefe');

        $this->get('/')
            ->assertOk()
            ->assertSee('#homepage-sections[data-home-theme="dark"] [data-home-section]:not([data-home-section="hero"]){color:var(--home-section-text,#e5edf8)}', false)
            ->assertSee('background-color:#123456;--home-section-text:#fefefe', false)
            ->assertSee(':is(.bg-white,.bg-slate-50,.bg-gray-50){background-color:#142238}', false);
    }

    public function test_homepage_section_color_controls_and_reviews_carousel_are_rendered(): void
    {
        Setting::set('homepage_theme_mode', 'light');
        Setting::set('homepage_learning_bg', '#fef3c7');
        Setting::set('homepage_learning_text', '#78350f');
        Setting::set('homepage_stats_bg', '#064e3b');
        Setting::set('homepage_stats_text', '#ffffff');
        Setting::set('homepage_testimonial_bg', '#1e1b4b');
        Setting::set('homepage_testimonial_text', '#f8fafc');
        Setting::set('homepage_online_batches_subtitle', 'Choose your next class');
        Setting::set('homepage_reviews_layout', 'carousel');

        $this->get('/')
            ->assertOk()
            ->assertSee("background:#fef3c7;--home-section-text:#78350f", false)
            ->assertSee("background:#064e3b;--home-section-text:#ffffff", false)
            ->assertSee("background:#1e1b4b;--home-section-text:#f8fafc", false)
            ->assertSee('Choose your next class')
            ->assertSee('data-home-carousel="reviews"', false)
            ->assertSee('data-home-carousel-layout="carousel"', false);
    }

    public function test_admin_can_customize_background_and_text_colors_for_all_homepage_sections(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'homepage_online_batches_bg' => '#fff7ed',
            'homepage_online_batches_text' => '#7c2d12',
            'homepage_featured_bg' => '#111827',
            'homepage_featured_text' => '#f8fafc',
            'homepage_latest_bg' => '#f1f5f9',
            'homepage_latest_text' => '#0f172a',
            'homepage_live_classes_bg' => '#ecfeff',
            'homepage_live_classes_text' => '#164e63',
            'homepage_exams_bg' => '#f8fafc',
            'homepage_exams_text' => '#0f172a',
            'homepage_teachers_bg' => '#f0fdf4',
            'homepage_teachers_text' => '#14532d',
            'homepage_board_results_bg' => '#064e3b',
            'homepage_board_results_text' => '#ffffff',
            'homepage_ebooks_bg' => '#ffffff',
            'homepage_ebooks_text' => '#0f172a',
            'homepage_admission_bg' => '#0f172a',
            'homepage_admission_text' => '#ffffff',
        ])->assertRedirect();

        foreach ([
            'homepage_online_batches_bg' => '#fff7ed',
            'homepage_online_batches_text' => '#7c2d12',
            'homepage_featured_bg' => '#111827',
            'homepage_featured_text' => '#f8fafc',
            'homepage_latest_bg' => '#f1f5f9',
            'homepage_latest_text' => '#0f172a',
            'homepage_live_classes_bg' => '#ecfeff',
            'homepage_live_classes_text' => '#164e63',
            'homepage_exams_bg' => '#f8fafc',
            'homepage_exams_text' => '#0f172a',
            'homepage_teachers_bg' => '#f0fdf4',
            'homepage_teachers_text' => '#14532d',
            'homepage_board_results_bg' => '#064e3b',
            'homepage_board_results_text' => '#ffffff',
            'homepage_ebooks_bg' => '#ffffff',
            'homepage_ebooks_text' => '#0f172a',
            'homepage_admission_bg' => '#0f172a',
            'homepage_admission_text' => '#ffffff',
        ] as $key => $value) {
            $this->assertSame($value, Setting::get($key));
        }

        $this->get('/')
            ->assertOk()
            ->assertSee('background-color:#fff7ed;--home-section-text:#7c2d12', false)
            ->assertSee('var(--home-section-text,#e5edf8)', false);
    }

    public function test_public_brand_palette_is_rendered_on_the_website(): void
    {
        Setting::set('site_primary_color', '#ea580c');
        Setting::set('site_secondary_color', '#9a3412');

        $this->get('/')
            ->assertOk()
            ->assertSee('--aec-primary: #ea580c', false)
            ->assertSee('--aec-primary-dark: #9a3412', false)
            ->assertSee('.bg-orange-700,.bg-orange-600,.bg-orange-500', false);
    }

    public function test_admin_can_save_and_render_homepage_section_order(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $order = ['hero', 'featured', 'categories', 'online_batches', 'stats', 'latest', 'live_classes', 'exams', 'teachers', 'board_results', 'reviews', 'ebooks', 'admission'];

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'homepage_show_categories' => '1',
            'homepage_show_featured' => '1',
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


    public function test_admin_slider_crud_toggle_and_archive_preserve_recoverable_media(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        Storage::fake('public');

        $this->actingAs($admin)->post(route('admin.sliders.store'), [
            'title' => 'Safe slider creation',
            'subtitle' => 'A slide with managed artwork',
            'button_text' => 'Browse courses',
            'button_url' => '/courses',
            'theme' => 'amber',
            'sort_order' => 1,
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('first-slide.webp'),
        ])->assertRedirect(route('admin.sliders.index'));

        $slider = Slider::where('title', 'Safe slider creation')->firstOrFail();
        $oldImage = $slider->image;
        Storage::disk('public')->assertExists($oldImage);

        $this->actingAs($admin)->put(route('admin.sliders.update', $slider), [
            'title' => 'Updated safe slider',
            'subtitle' => 'Updated slide',
            'button_text' => 'Visit center',
            'button_url' => 'https://example.com/admissions',
            'theme' => 'indigo',
            'sort_order' => 2,
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('replacement-slide.webp'),
        ])->assertRedirect(route('admin.sliders.index'));

        $slider->refresh();
        $newImage = $slider->image;
        $this->assertNotSame($oldImage, $newImage);
        Storage::disk('public')->assertExists($oldImage);
        $this->assertDatabaseHas('media_assets', ['disk' => 'public', 'path' => $oldImage]);
        Storage::disk('public')->assertExists($newImage);
        $this->assertSame('https://example.com/admissions', $slider->button_url);

        $this->post(route('admin.sliders.toggle', $slider))
            ->assertRedirect();
        $this->assertFalse($slider->fresh()->is_active);

        $this->delete(route('admin.sliders.destroy', $slider))
            ->assertRedirect();
        $this->assertSoftDeleted('sliders', ['id' => $slider->id]);
        Storage::disk('public')->assertExists($newImage);
    }

    public function test_slider_button_rejects_unsafe_and_protocol_relative_urls(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $before = Slider::count();

        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', '//evil.example/path'] as $unsafeUrl) {
            $this->actingAs($admin)->from(route('admin.sliders.create'))
                ->post(route('admin.sliders.store'), [
                    'title' => 'Unsafe slide',
                    'button_url' => $unsafeUrl,
                    'theme' => 'emerald',
                    'is_active' => '1',
                ])
                ->assertRedirect(route('admin.sliders.create'))
                ->assertSessionHasErrors('button_url');
        }

        $this->assertSame($before, Slider::count());
        $this->assertDatabaseMissing('sliders', ['title' => 'Unsafe slide']);
    }

    public function test_non_admin_cannot_manage_homepage_sliders(): void
    {
        $student = User::where('role', 'student')->firstOrFail();
        $before = Slider::count();

        $this->actingAs($student)->get(route('admin.sliders.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.sliders.store'), [
            'title' => 'Forbidden slide',
            'button_url' => '/courses',
            'theme' => 'emerald',
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertSame($before, Slider::count());
        $this->assertDatabaseMissing('sliders', ['title' => 'Forbidden slide']);
    }

    public function test_homepage_and_dashboard_cta_settings_reject_unsafe_urls(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        foreach ([
            'homepage_admission_button_url' => 'javascript:alert(1)',
            'dashboard_banner_button_url' => 'data:text/html,unsafe',
        ] as $field => $unsafeUrl) {
            $this->actingAs($admin)->from('/admin/settings')
                ->post(route('admin.settings.update'), [$field => $unsafeUrl])
                ->assertRedirect('/admin/settings')
                ->assertSessionHasErrors($field);
        }
    }

    public function test_homepage_cta_settings_accept_safe_internal_and_https_destinations(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'homepage_admission_button_url' => '/coaching-admission',
            'dashboard_banner_button_url' => 'https://example.com/student-help',
        ])->assertRedirect();

        $this->assertSame('/coaching-admission', Setting::get('homepage_admission_button_url'));
        $this->assertSame('https://example.com/student-help', Setting::get('dashboard_banner_button_url'));
    }

    public function test_admin_configured_homepage_section_copy_is_rendered(): void
    {
        Setting::set('homepage_latest_title', 'New learning courses this month');
        Setting::set('homepage_latest_subtitle', 'Fresh courses for every learner.');
        Setting::set('homepage_show_latest', '1');

        $this->get('/')
            ->assertOk()
            ->assertSee('New learning courses this month')
            ->assertSee('Fresh courses for every learner.');
    }

    public function test_homepage_theme_and_scroll_animation_settings_render_without_changing_the_slider(): void
    {
        Setting::set('homepage_theme_mode', 'dark');
        Setting::set('homepage_animation_style', 'rise');
        Setting::set('homepage_animation_speed', '700');

        $this->get('/')
            ->assertOk()
            ->assertSee('data-home-theme="dark"', false)
            ->assertSee('data-home-animation="rise"', false)
            ->assertSee('data-home-animation-speed="700"', false)
            ->assertSee('id="heroSlides"', false)
            ->assertSee('data-hero-slide', false)
            ->assertSee("savedOrder.filter(key => key !== 'hero')", false)
            ->assertSee('const fixedHero = sections.get(\'hero\')', false);
    }



    public function test_settings_logo_upload_is_also_registered_in_the_shared_media_library(): void
    {
        Storage::fake('public');
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'logo' => UploadedFile::fake()->image('center-logo.png'),
        ])->assertRedirect();

        $asset = MediaAsset::query()->where('title', 'Site logo')->firstOrFail();

        $this->assertSame('public', $asset->disk);
        $this->assertStringStartsWith('brand/', $asset->path);
        $this->assertSame('center-logo.png', $asset->original_name);
        Storage::disk('public')->assertExists($asset->path);
        $this->assertSame($asset->path, Setting::get('logo'));
    }

    public function test_admin_homepage_section_controls_use_the_new_default_sequence(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-section-key="hero"',
                'data-section-key="online_batches"',
                'data-section-key="stats"',
                'data-section-key="categories"',
                'data-section-key="featured"',
            ], false)
            ->assertSee('draggable="false" data-section-key="hero" data-fixed-section="true"', false)
            ->assertSee('Your existing slider (fixed)', false);
    }

}

