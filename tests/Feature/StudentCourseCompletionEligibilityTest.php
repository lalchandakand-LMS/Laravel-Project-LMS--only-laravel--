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

    public function test_certificate_page_reuses_the_existing_certificate_for_a_completed_course(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Certificate Reuse Course',
            'slug' => 'certificate-reuse-course',
            'price' => 1000,
            'is_published' => true,
            'has_certificate' => true,
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Completed Certificate Lesson',
            'type' => 'text',
            'position' => 1,
            'is_published' => true,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('student.certificates.show', $course->slug))
            ->assertOk();

        $issued = Certificate::where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->get(route('student.certificates.show', $course->slug))->assertOk();

        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame($issued->code, Certificate::firstOrFail()->code);
    }

    public function test_database_prevents_duplicate_certificates_for_the_same_student_and_course(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Unique Certificate Course',
            'slug' => 'unique-certificate-course',
            'price' => 1000,
            'is_published' => true,
        ]);

        Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'issued_at' => now(),
        ]);

        try {
            Certificate::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'issued_at' => now(),
            ]);
            $this->fail('The database should reject a second certificate for the same student and course.');
        } catch (QueryException) {
            $this->assertDatabaseCount('certificates', 1);
        }
    }
}\n