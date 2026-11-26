<?php

namespace Tests\Feature;

use App\Models\Slider;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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

    public function test_admin_can_move_a_student_account_to_recycle_bin_and_restore_it(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::where('email', 'student@lms.test')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $student))
            ->assertRedirect();

        $this->assertSoftDeleted('users', [
            'id' => $student->id,
            'deleted_by' => $admin->id,
        ]);

        $this->post(route('admin.recycle-bin.restore', ['type' => 'users', 'id' => $student->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'deleted_at' => null]);
    }

    public function test_permanent_delete_is_blocked_when_course_has_enrolment_history(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::where('email', 'student@lms.test')->firstOrFail();
        $course = Course::create(['title' => 'Protected course history', 'slug' => 'protected-course-history']);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 100,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.courses.destroy', $course))
            ->assertRedirect();

        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'courses', 'id' => $course->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $student->id]);
    }

    public function test_expired_safe_records_are_pruned_but_linked_course_history_is_retained(): void
    {
        $student = User::where('email', 'student@lms.test')->firstOrFail();
        $slider = Slider::create(['title' => 'Expired slider', 'is_active' => false]);
        $slider->delete();
        DB::table('sliders')->where('id', $slider->id)->update(['deleted_at' => now()->subDays(31)]);

        $course = Course::create(['title' => 'Protected old course', 'slug' => 'protected-old-course']);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 50,
        ]);
        $course->delete();
        DB::table('courses')->where('id', $course->id)->update(['deleted_at' => now()->subDays(31)]);

        Artisan::call('recycle-bin:prune', ['--days' => 30]);

        $this->assertDatabaseMissing('sliders', ['id' => $slider->id]);
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $student->id]);
    }

    public function test_recycle_bin_rejects_unknown_model_types(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/recycle-bin/not-a-model')
            ->assertNotFound();
    }

    public function test_archived_lesson_and_slider_keep_media_until_permanent_deletion(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::create(['title' => 'Media recovery course', 'slug' => 'media-recovery-course']);

        $sliderImage = UploadedFile::fake()->image('recoverable-slider.jpg')->store('sliders', 'public');
        $slider = Slider::create([
            'title' => 'Slider with recoverable media',
            'image' => $sliderImage,
            'is_active' => true,
        ]);

        $lessonFile = UploadedFile::fake()->create('recoverable-lesson.pdf', 40, 'application/pdf')
            ->store('lessons', 'local');
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Lesson with recoverable media',
            'type' => 'pdf',
            'attachment' => $lessonFile,
        ]);

        $this->actingAs($admin)->delete(route('admin.sliders.destroy', $slider))->assertRedirect();
        $this->delete(route('admin.lessons.destroy', $lesson))->assertRedirect();
        Storage::disk('public')->assertExists($sliderImage);
        Storage::disk('local')->assertExists($lessonFile);

        $this->post(route('admin.recycle-bin.restore', ['type' => 'sliders', 'id' => $slider->id]))->assertRedirect();
        $this->post(route('admin.recycle-bin.restore', ['type' => 'lessons', 'id' => $lesson->id]))->assertRedirect();
        Storage::disk('public')->assertExists($sliderImage);
        Storage::disk('local')->assertExists($lessonFile);

        $this->delete(route('admin.sliders.destroy', $slider))->assertRedirect();
        $this->delete(route('admin.lessons.destroy', $lesson))->assertRedirect();
        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'sliders', 'id' => $slider->id]))->assertRedirect();
        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'lessons', 'id' => $lesson->id]))->assertRedirect();

        Storage::disk('public')->assertMissing($sliderImage);
        Storage::disk('local')->assertMissing($lessonFile);
    }

}
