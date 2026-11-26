<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCourseCompletionEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_progress_ignores_unpublished_draft_lessons(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Published Curriculum Course',
            'slug' => 'published-curriculum-course',
            'price' => 1000,
            'is_published' => true,
        ]);

        $first = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Published Lesson One',
            'type' => 'text',
            'position' => 1,
            'is_published' => true,
        ]);
        $second = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Published Lesson Two',
            'type' => 'text',
            'position' => 2,
            'is_published' => true,
        ]);
        Lesson::create([
            'course_id' => $course->id,
            'title' => 'Draft Lesson',
            'type' => 'text',
            'position' => 3,
            'is_published' => false,
        ]);

        foreach ([$first, $second] as $lesson) {
            LessonProgress::create([
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
                'completed_at' => now(),
            ]);
        }

        $this->assertSame(100, $course->progressFor($student));
    }

    public function test_expired_enrollment_cannot_create_a_new_certificate(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Expired Certificate Course',
            'slug' => 'expired-certificate-course',
            'price' => 1000,
            'is_published' => true,
            'has_certificate' => true,
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Completed Lesson',
            'type' => 'text',
            'position' => 1,
            'is_published' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now()->subDays(30),
            'expires_at' => now()->subMinute(),
        ]);
        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('student.certificates.show', $course->slug))
            ->assertForbidden();

        $this->assertDatabaseMissing('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }
}
