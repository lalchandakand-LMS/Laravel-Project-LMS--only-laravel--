<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_create_and_update_public_menu_items(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->get('/admin/menu-settings')
            ->assertOk()
            ->assertSee('Menu Settings')
            ->assertSee('Header menu')
            ->assertSee('Footer menu');

        $this->actingAs($admin)->post('/admin/menu-settings', [
            'label' => 'About our center',
            'url' => '/about',
            'location' => 'header',
            'sort_order' => 10,
            'is_enabled' => 1,
            'show_desktop' => 1,
            'show_mobile' => 1,
        ])->assertRedirect();

        $item = MenuItem::where('label', 'About our center')->firstOrFail();
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'url' => '/about']);

        $this->actingAs($admin)->put('/admin/menu-settings/'.$item->id, [
            'label' => 'About Alom',
            'url' => '/about',
            'location' => 'header',
            'sort_order' => 2,
            'is_enabled' => 1,
            'show_desktop' => 1,
            'show_mobile' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'label' => 'About Alom']);
        $this->get('/')->assertOk()->assertSee('About Alom');
    }

    public function test_parent_menu_opens_accessible_dropdowns_on_desktop_and_mobile(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $parent = MenuItem::create([
            'label' => 'Learning',
            'url' => '/courses',
            'location' => 'header',
            'sort_order' => 1,
            'is_enabled' => true,
            'show_desktop' => true,
            'show_mobile' => true,
        ]);
        MenuItem::create([
            'label' => 'All courses',
            'url' => '/courses',
            'location' => 'header',
            'parent_id' => $parent->id,
            'sort_order' => 1,
            'is_enabled' => true,
            'show_desktop' => true,
            'show_mobile' => true,
        ]);

        $response = $this->actingAs($admin)->get('/')->assertOk();
        $response->assertSee('<details class="group relative">', false)
            ->assertSee('<details class="group rounded-xl">', false)
            ->assertSee('<summary', false)
            ->assertSee('Learning')
            ->assertSee('All courses');
    }

    public function test_non_admin_cannot_manage_menu_settings(): void
    {
        $student = User::where('email', 'student@lms.test')->firstOrFail();
        $this->actingAs($student)->get('/admin/menu-settings')->assertForbidden();
    }

    public function test_menu_links_reject_unsafe_schemes(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $this->actingAs($admin)->post('/admin/menu-settings', [
            'label' => 'Unsafe link',
            'url' => 'javascript:alert(1)',
            'location' => 'header',
            'sort_order' => 10,
        ])->assertStatus(422);
    }
}
