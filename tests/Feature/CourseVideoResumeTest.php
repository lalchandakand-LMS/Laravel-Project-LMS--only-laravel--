<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseVideoResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function enrolledLesson(): array
    {
        $student = User::where('email', 'student@lms.test')->firstOrFail();
        $course = Course::whereHas('lessons')->firstOrFail();
        $lesson = $course->lessons()->firstOrFail();

        $course->update(['is_published' => true]);
        $lesson->update([
            'is_published' => true,
            'is_free_preview' => false,
            'type' => 'video',
            'video_url' => 'https://youtu.be/abcdefghijk',
        ]);
        LessonProgress::where('user_id', $student->id)->where('lesson_id', $lesson->id)->delete();
        Enrollment::updateOrCreate(
            ['user_id' => $student->id, 'course_id' => $course->id],
            ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => null]
        );

        return [$student, $course->fresh(), $lesson->fresh()];
    }

    public function test_player_restores_saved_video_position_in_the_player_markup(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();
        LessonProgress::updateOrCreate(
            ['user_id' => $student->id, 'lesson_id' => $lesson->id],
            ['watched_seconds' => 245]
        );

        $this->actingAs($student)
            ->get(route('student.learn.lesson', [$course->slug, $lesson->id]))
            ->assertOk()
            ->assertSee('data-resume-seconds="245"', false)
            ->assertSee('Your saved resume point: 00:04:05');
    }

    public function test_video_heartbeat_persists_the_furthest_position_without_regression(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();

        $this->actingAs($student)
            ->postJson(route('student.learn.heartbeat', [$course->slug, $lesson->id]), ['seconds' => 135])
            ->assertOk()
            ->assertJson(['ok' => true, 'watched_seconds' => 135]);

        $this->actingAs($student)
            ->postJson(route('student.learn.heartbeat', [$course->slug, $lesson->id]), ['seconds' => 75])
            ->assertOk()
            ->assertJson(['ok' => true, 'watched_seconds' => 135]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 135,
        ]);
    }


    public function test_heartbeat_accepts_the_maximum_supported_video_position(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();

        $this->actingAs($student)
            ->postJson(route('student.learn.heartbeat', [$course->slug, $lesson->id]), ['seconds' => 86400])
            ->assertOk()
            ->assertJson(['ok' => true, 'watched_seconds' => 86400]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 86400,
        ]);
    }

    public function test_video_heartbeat_rejects_invalid_positions(): void
    {
        [$student, $course, $lesson] = $this->enrolledLesson();

        $this->actingAs($student)
            ->postJson(route('student.learn.heartbeat', [$course->slug, $lesson->id]), ['seconds' => -1])
            ->assertUnprocessable();
    }
}
