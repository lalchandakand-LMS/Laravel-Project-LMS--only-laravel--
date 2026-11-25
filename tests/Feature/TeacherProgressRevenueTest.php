<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherProgressRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_sees_scoped_student_progress_and_activity(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Teacher Analytics Course',
            'slug' => 'teacher-analytics-course',
            'price' => 500,
            'is_published' => true,
            'instructor_id' => $teacher->id,
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Completed lesson',
            'type' => 'text',
            'content' => 'Lesson content',
            'position' => 1,
            'is_published' => true,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 500,
            'enrolled_at' => now(),
        ]);
        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
            'watched_seconds' => 180,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.students'))
            ->assertOk()
            ->assertSee('Course progress')
            ->assertSee('100%');

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', $student))
            ->assertOk()
            ->assertSee('1 / 1 lessons completed')
            ->assertSee('100%');
    }

    public function test_teacher_revenue_is_allocated_by_course_value_and_refunds(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $otherTeacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $ownedCourse = Course::create([
            'title' => 'Owned course',
            'slug' => 'owned-course-revenue',
            'price' => 300,
            'is_published' => true,
            'instructor_id' => $teacher->id,
        ]);
        $otherCourse = Course::create([
            'title' => 'Other course',
            'slug' => 'other-course-revenue',
            'price' => 700,
            'is_published' => true,
            'instructor_id' => $otherTeacher->id,
        ]);

        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'paid',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);
        $order->items()->createMany([
            ['item_type' => 'course', 'item_id' => $ownedCourse->id, 'title' => $ownedCourse->title, 'price' => 300, 'qty' => 1],
            ['item_type' => 'course', 'item_id' => $otherCourse->id, 'title' => $otherCourse->title, 'price' => 700, 'qty' => 1],
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'TEACHER-REV-1000',
            'amount' => 1000,
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        PaymentAdjustment::create([
            'order_id' => $order->id,
            'type' => 'refund',
            'amount' => 100,
            'reason' => 'Partial refund',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.earnings'))
            ->assertOk()
            ->assertSee('Net lifetime revenue')
            ->assertSee('270.00')
            ->assertSee('Owned course')
            ->assertDontSee('Other course');
    }
}
