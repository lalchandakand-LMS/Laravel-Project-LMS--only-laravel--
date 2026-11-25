<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\OrderService;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAccessSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithStudent(): array
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Scheduled Course',
            'slug' => 'scheduled-course',
            'price' => 1000,
            'is_published' => true,
            'access_duration_days' => 30,
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'First lesson',
            'type' => 'text',
            'content' => 'First lesson content',
            'position' => 1,
            'is_published' => true,
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
        ]);

        return [$student, $course, $lesson, $enrollment];
    }

    public function test_expired_enrollment_cannot_open_paid_lessons(): void
    {
        [$student, $course, $lesson, $enrollment] = $this->courseWithStudent();
        $enrollment->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertForbidden();
    }

    public function test_drip_lesson_stays_locked_until_release_time(): void
    {
        [$student, $course, $lesson] = $this->courseWithStudent();
        $lesson->update(['available_at' => now()->addDay()]);

        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertForbidden();

        $lesson->update(['available_at' => now()->subMinute()]);
        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertOk();
    }

    public function test_prerequisite_lesson_must_be_completed_before_unlock(): void
    {
        [$student, $course, $prerequisite] = $this->courseWithStudent();
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Second lesson',
            'type' => 'text',
            'content' => 'Second lesson content',
            'position' => 2,
            'is_published' => true,
            'prerequisite_lesson_id' => $prerequisite->id,
        ]);

        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertForbidden();

        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $prerequisite->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertOk();
    }

    public function test_batch_status_and_branch_assignment_control_batch_course_access(): void
    {
        [$student, $course, $lesson, $enrollment] = $this->courseWithStudent();
        $branch = Branch::create(['name' => 'Batch Branch', 'code' => 'BATCH-BR', 'is_active' => true]);
        $student->update(['branch_id' => $branch->id]);
        $batch = Batch::create([
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'name' => 'Upcoming batch',
            'code' => 'UPCOMING-1',
            'status' => 'upcoming',
        ]);
        $enrollment->update(['batch_id' => $batch->id]);

        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertForbidden();

        $batch->update(['status' => 'ongoing']);
        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertOk();

        $otherBranch = Branch::create(['name' => 'Other Branch', 'code' => 'OTHER-BR', 'is_active' => true]);
        $student->update(['branch_id' => $otherBranch->id]);
        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertForbidden();
    }

    public function test_paid_course_enrollment_receives_configured_expiry(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'title' => 'Thirty Day Course',
            'slug' => 'thirty-day-course',
            'price' => 500,
            'is_published' => true,
            'access_duration_days' => 30,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);
        $order->items()->create([
            'item_type' => 'course',
            'item_id' => $course->id,
            'title' => $course->title,
            'price' => 500,
            'qty' => 1,
        ]);

        OrderService::fulfill($order);

        $enrollment = $student->enrollments()->where('course_id', $course->id)->firstOrFail();
        $this->assertNotNull($enrollment->expires_at);
        $this->assertTrue($enrollment->expires_at->between(now()->addDays(29), now()->addDays(31)));
    }
}
