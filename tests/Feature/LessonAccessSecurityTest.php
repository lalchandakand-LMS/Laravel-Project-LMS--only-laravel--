<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function student(): User
    {
        return User::where('role', 'student')->firstOrFail();
    }

    private function lessonFixture(): array
    {
        $course = Course::whereHas('lessons')->firstOrFail();
        $lesson = $course->lessons()->firstOrFail();
        $course->update(['is_published' => true]);
        $lesson->update(['is_published' => true, 'is_free_preview' => false]);

        return [$course->fresh(), $lesson->fresh()];
    }

    public function test_paid_lesson_requires_active_non_expired_enrollment(): void
    {
        [$course, $lesson] = $this->lessonFixture();
        $student = $this->student();

        Enrollment::updateOrCreate(
            ['user_id' => $student->id, 'course_id' => $course->id],
            ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => now()->subMinute()]
        );

        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertForbidden();

        Enrollment::where('user_id', $student->id)->where('course_id', $course->id)
            ->update(['expires_at' => now()->addDay()]);

        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertOk();
    }

    public function test_unpublished_lesson_cannot_be_opened_by_enrolled_student(): void
    {
        [$course, $lesson] = $this->lessonFixture();
        $student = $this->student();

        Enrollment::updateOrCreate(
            ['user_id' => $student->id, 'course_id' => $course->id],
            ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => null]
        );
        $lesson->update(['is_published' => false]);

        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertNotFound();
    }

    public function test_free_preview_is_available_without_enrollment_but_paid_lesson_is_not(): void
    {
        [$course, $lesson] = $this->lessonFixture();
        $student = $this->student();
        Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->delete();

        $lesson->update(['is_free_preview' => true]);
        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertOk();

        $lesson->update(['is_free_preview' => false]);
        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertForbidden();
    }

    public function test_lesson_from_another_course_cannot_be_used_to_complete_or_heartbeat(): void
    {
        $courses = Course::whereHas('lessons')->take(2)->get();
        $this->assertCount(2, $courses);
        $lesson = $courses[1]->lessons()->firstOrFail();
        $student = $this->student();

        Enrollment::updateOrCreate(
            ['user_id' => $student->id, 'course_id' => $courses[0]->id],
            ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => null]
        );

        $this->actingAs($student)
            ->post(route('student.learn.complete', [$courses[0]->slug, $lesson->id]))
            ->assertNotFound();

        $this->actingAs($student)
            ->post(route('student.learn.heartbeat', [$courses[0]->slug, $lesson->id]), ['seconds' => 30])
            ->assertNotFound();
    }

    public function test_invalid_cross_course_section_does_not_leave_an_uploaded_lesson_attachment(): void
    {
        Storage::fake('local');
        $admin = User::where('role', 'admin')->firstOrFail();
        $course = Course::whereHas('sections')->firstOrFail();
        $otherSection = Section::where('course_id', '!=', $course->id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.lessons.store'), [
            'course_id' => $course->id,
            'section_id' => $otherSection->id,
            'title' => 'Invalid cross-course lesson',
            'type' => 'pdf',
            'attachment' => UploadedFile::fake()->create('lesson.pdf', 30, 'application/pdf'),
        ])->assertStatus(422);

        $this->assertSame([], Storage::disk('local')->allFiles('lessons'));
    }

}
