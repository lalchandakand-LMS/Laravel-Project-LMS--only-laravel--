<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Order;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseArchiveRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@lms.test')->firstOrFail();
    }

    public function test_admin_can_create_and_update_a_course(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.courses.store'), [
                'title' => 'Course CRUD acceptance',
                'description' => 'Created through the admin course form.',
                'price' => 1200,
                'discount_price' => 950,
                'level' => 'intermediate',
                'language' => 'Bangla',
                'is_published' => 1,
            ])
            ->assertSessionHasNoErrors();

        $course = Course::where('title', 'Course CRUD acceptance')->firstOrFail();
        $this->assertSame(1200.0, (float) $course->price);
        $this->assertSame(950.0, (float) $course->discount_price);
        $this->assertTrue($course->is_published);

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), [
                'title' => 'Updated course title',
                'description' => 'Updated course description.',
                'price' => 1500,
                'discount_price' => 1100,
                'level' => 'advanced',
                'language' => 'English',
                'is_published' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Updated course title',
            'price' => 1500,
            'discount_price' => 1100,
            'language' => 'English',
            'is_published' => true,
        ]);
    }

    public function test_archiving_and_restoring_course_preserves_learning_financial_and_media_history(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->admin();
        $student = User::where('email', 'student@lms.test')->firstOrFail();
        $course = Course::create([
            'title' => 'Course with protected history',
            'slug' => 'course-with-protected-history',
            'price' => 900,
            'is_published' => true,
        ]);

        $thumbnail = UploadedFile::fake()->image('course-thumbnail.jpg')->store('courses', 'public');
        $course->update(['thumbnail' => $thumbnail]);

        $section = Section::create([
            'course_id' => $course->id,
            'title' => 'Preserved section',
            'position' => 1,
        ]);
        $attachment = UploadedFile::fake()->create('lesson-resource.pdf', 12, 'application/pdf')
            ->store('lessons', 'local');
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'section_id' => $section->id,
            'title' => 'Preserved lesson',
            'type' => 'pdf',
            'attachment' => $attachment,
            'position' => 1,
            'is_published' => true,
        ]);

        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 900,
            'enrolled_at' => now(),
        ]);
        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 180,
            'completed_at' => now(),
        ]);
        $certificate = Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'code' => 'CERT-COURSE-ARCHIVE',
            'issued_at' => now(),
        ]);

        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 900,
            'discount' => 0,
            'total' => 900,
            'status' => 'paid',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);
        $order->items()->create([
            'item_type' => 'course',
            'item_id' => $course->id,
            'title' => $course->title,
            'price' => 900,
            'qty' => 1,
        ]);
        DB::table('payments')->insert([
            'order_id' => $order->id,
            'gateway' => 'bkash',
            'transaction_id' => 'COURSE-ARCHIVE-TXN',
            'amount' => 900,
            'status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.courses.destroy', $course))
            ->assertRedirect(route('admin.courses.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('courses', ['id' => $course->id, 'deleted_by' => $admin->id]);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('lesson_progress', ['user_id' => $student->id, 'lesson_id' => $lesson->id]);
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'transaction_id' => 'COURSE-ARCHIVE-TXN']);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'item_id' => $course->id, 'item_type' => 'course']);
        Storage::disk('public')->assertExists($thumbnail);
        Storage::disk('local')->assertExists($attachment);

        $this->actingAs($admin)
            ->post(route('admin.recycle-bin.restore', ['type' => 'courses', 'id' => $course->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'deleted_at' => null, 'deleted_by' => null]);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('lesson_progress', ['user_id' => $student->id, 'lesson_id' => $lesson->id]);
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'transaction_id' => 'COURSE-ARCHIVE-TXN']);
        Storage::disk('public')->assertExists($thumbnail);
        Storage::disk('local')->assertExists($attachment);
    }

    public function test_permanent_course_deletion_is_blocked_by_assignments_and_batches(): void
    {
        $admin = $this->admin();
        $course = Course::create([
            'title' => 'Course with assignments and batch',
            'slug' => 'course-with-assignments-and-batch',
        ]);

        DB::table('assignments')->insert([
            'course_id' => $course->id,
            'title' => 'Protected assignment',
            'total_marks' => 100,
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('batches')->insert([
            'course_id' => $course->id,
            'name' => 'Protected batch',
            'code' => 'PROTECTED-BATCH',
            'status' => 'ongoing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.courses.destroy', $course))
            ->assertRedirect();

        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'courses', 'id' => $course->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('assignments', ['course_id' => $course->id, 'title' => 'Protected assignment']);
        $this->assertDatabaseHas('batches', ['course_id' => $course->id, 'code' => 'PROTECTED-BATCH']);
    }
}
