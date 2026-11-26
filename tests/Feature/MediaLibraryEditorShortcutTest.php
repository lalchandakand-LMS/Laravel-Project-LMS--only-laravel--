<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaLibraryEditorShortcutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_media_library_shortcuts_are_visible_to_admins_but_not_instructors(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'is_active' => true,
        ]);
        $course = Course::whereHas('lessons')->firstOrFail();
        $course->update(['instructor_id' => $instructor->id]);
        $lesson = $course->lessons()->firstOrFail();
        $mediaLibraryUrl = route('admin.media.index');

        $this->actingAs($admin)
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertSee($mediaLibraryUrl);

        $this->actingAs($admin)
            ->get(route('admin.lessons.edit', $lesson))
            ->assertOk()
            ->assertSee($mediaLibraryUrl);

        $this->actingAs($instructor)
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertDontSee($mediaLibraryUrl);

        $this->actingAs($instructor)
            ->get(route('admin.lessons.edit', $lesson))
            ->assertOk()
            ->assertDontSee($mediaLibraryUrl);
    }
}
