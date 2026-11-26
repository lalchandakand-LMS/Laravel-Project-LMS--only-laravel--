<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDesignStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_design_studio_preferences(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'admin_theme_preset' => 'orange',
            'admin_card_radius' => '22',
            'admin_brand_title' => 'Alom Learning',
            'admin_brand_subtitle' => 'Admin Console',
            'admin_sidebar_promo_title' => 'Admissions',
            'admin_sidebar_promo_body' => 'Review new admissions.',
            'admin_sidebar_promo_button' => 'Open admissions',
            'admin_sidebar_promo_url' => '/admin/coaching-admissions',
            'admin_sidebar_promo_enabled' => '1',
        ])->assertRedirect();

        $this->assertSame('orange', Setting::get('admin_theme_preset'));
        $this->assertSame('22', (string) Setting::get('admin_card_radius'));
        $this->assertSame('Alom Learning', Setting::get('admin_brand_title'));
        $this->assertSame('1', Setting::get('admin_sidebar_promo_enabled'));
    }

    public function test_design_studio_rejects_unsafe_promo_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'admin_theme_preset' => 'orange',
            'admin_card_radius' => '18',
            'admin_sidebar_promo_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('admin_sidebar_promo_url');
    }
}
