<?php

namespace Tests\Feature;

use App\Models\Slider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_restore_a_deleted_slider_and_deletion_actor_is_recorded(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $slider = Slider::create([
            'title' => 'Recoverable homepage banner',
            'subtitle' => 'Test slide',
            'button_text' => 'Explore',
            'button_url' => '/courses',
            'theme' => 'emerald',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.sliders.destroy', $slider))
            ->assertRedirect();

        $this->assertSoftDeleted('sliders', [
            'id' => $slider->id,
            'deleted_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.recycle-bin.index', ['type' => 'sliders']))
            ->assertOk()
            ->assertSee('Recoverable homepage banner');

        $this->post(route('admin.recycle-bin.restore', ['type' => 'sliders', 'id' => $slider->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('sliders', [
            'id' => $slider->id,
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
    }

    public function test_recycle_bin_rejects_unknown_model_types(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/recycle-bin/not-a-model')
            ->assertNotFound();
    }
}
