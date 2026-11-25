<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryBatchLevelManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_manage_levels_and_assign_them_to_courses(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.levels.index'))->assertOk();
        $this->actingAs($admin)->post(route('admin.levels.store'), [
            'name' => 'Pre-Intermediate', 'description' => 'Build foundational fluency.',
            'position' => 2, 'is_active' => 1,
        ])->assertRedirect(route('admin.levels.index'));

        $level = Level::where('name', 'Pre-Intermediate')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.levels.edit', $level))->assertOk();
        $this->actingAs($admin)->put(route('admin.levels.update', $level), [
            'name' => 'Elementary Plus', 'description' => 'Updated description.',
            'position' => 3, 'is_active' => 1,
        ])->assertRedirect(route('admin.levels.index'));

        $course = Course::firstOrFail();
        $this->actingAs($admin)->put(route('admin.courses.update', $course), [
            'title' => $course->title, 'price' => $course->price,
            'level' => 'beginner', 'level_id' => $level->id,
            'language' => 'Bangla',
        ])->assertRedirect(route('admin.courses.edit', $course));

        $this->assertSame($level->id, (int) $course->fresh()->level_id);
        $this->assertSame($level->id, (int) $course->fresh()->courseLevel->id);

        $this->actingAs($admin)->delete(route('admin.levels.destroy', $level))->assertRedirect(route('admin.levels.index'));
        $this->assertNotNull(Level::withTrashed()->findOrFail($level->id)->deleted_at);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'level_id' => $level->id]);
        $this->actingAs($admin)->get(route('admin.recycle-bin.index', ['type' => 'levels']))->assertOk();
    }

    public function test_admin_can_create_update_and_recycle_batches_without_losing_enrollments(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();
        $student = User::where('role', 'student')->firstOrFail();
        $teacher = User::where('role', 'instructor')->firstOrFail();

        $level = Level::create(['name' => 'Advanced', 'position' => 4, 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.batches.index'))->assertOk();
        $this->actingAs($admin)->post(route('admin.batches.store'), [
            'name' => 'Evening Spoken English', 'code' => 'SPOKEN-EVE-26',
            'course_id' => $course->id, 'level_id' => $level->id,
            'instructor_id' => $teacher->id, 'status' => 'upcoming',
            'starts_at' => now()->addDay()->toDateString(),
            'ends_at' => now()->addMonths(2)->toDateString(),
            'schedule_days' => 'Sun, Tue, Thu', 'schedule_time' => '7 PM',
            'capacity' => 30, 'description' => 'Evening class.',
        ])->assertRedirect(route('admin.batches.index'));

        $batch = Batch::where('code', 'SPOKEN-EVE-26')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.batches.edit', $batch))->assertOk();
        $this->actingAs($admin)->put(route('admin.batches.update', $batch), [
            'name' => 'Evening Spoken English A', 'code' => 'SPOKEN-EVE-26',
            'course_id' => $course->id, 'level_id' => $level->id,
            'instructor_id' => $teacher->id, 'status' => 'ongoing', 'capacity' => 35,
        ])->assertRedirect(route('admin.batches.index'));

        $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
        if (! $enrollment) {
            $enrollment = Enrollment::create([
                'user_id' => $student->id, 'course_id' => $course->id,
                'status' => 'active', 'enrolled_at' => now(),
            ]);
        }
        $enrollment->update(['batch_id' => $batch->id]);

        $this->actingAs($admin)->delete(route('admin.batches.destroy', $batch))->assertRedirect(route('admin.batches.index'));
        $this->assertNotNull(Batch::withTrashed()->findOrFail($batch->id)->deleted_at);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'batch_id' => $batch->id]);
        $this->actingAs($admin)->get(route('admin.recycle-bin.index', ['type' => 'batches']))->assertOk();
    }

    public function test_instructors_cannot_manage_categories_batches_or_levels(): void
    {
        $teacher = User::where('email', 'teacher0@lms.test')->firstOrFail();

        $this->actingAs($teacher)->get(route('admin.batches.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.levels.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.categories.index'))->assertForbidden();
    }
}
