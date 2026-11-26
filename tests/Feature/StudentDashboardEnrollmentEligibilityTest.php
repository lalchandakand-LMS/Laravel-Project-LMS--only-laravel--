<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardEnrollmentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_only_lists_exams_for_active_unexpired_enrollments(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $activeCourse = Course::create([
            'title' => 'Active Dashboard Course',
            'slug' => 'active-dashboard-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $expiredCourse = Course::create([
            'title' => 'Expired Dashboard Course',
            'slug' => 'expired-dashboard-course',
            'price' => 1000,
            'is_published' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $activeCourse->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now()->subDay(),
            'expires_at' => now()->addDays(5),
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $expiredCourse->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now()->subDays(40),
            'expires_at' => now()->subMinute(),
        ]);

        Exam::create([
            'course_id' => $activeCourse->id,
            'title' => 'Available Course Exam',
            'duration_minutes' => 30,
            'total_marks' => 20,
            'pass_marks' => 10,
            'is_free' => false,
            'is_published' => true,
        ]);
        Exam::create([
            'course_id' => $expiredCourse->id,
            'title' => 'Expired Course Exam',
            'duration_minutes' => 30,
            'total_marks' => 20,
            'pass_marks' => 10,
            'is_free' => false,
            'is_published' => true,
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Available Course Exam')
            ->assertDontSee('Expired Course Exam')
            ->assertDontSee('Expired Dashboard Course');
    }
}
