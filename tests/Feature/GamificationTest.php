<?php

namespace Tests\Feature;

use App\Models\AttemptAnswer;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\GamificationEvent;
use App\Models\Lesson;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_lesson_completion_awards_xp_only_once(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'XP Course',
            'slug' => 'xp-course',
            'price' => 500,
            'is_published' => true,
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'First XP Lesson',
            'type' => 'text',
            'content' => 'Learn a new word.',
            'position' => 1,
            'is_published' => true,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 500,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(10),
        ]);

        $this->actingAs($student)
            ->post(route('student.learn.complete', [$course->slug, $lesson->id]))
            ->assertRedirect();

        $this->actingAs($student)
            ->post(route('student.learn.complete', [$course->slug, $lesson->id]))
            ->assertRedirect();

        $this->assertSame(1, GamificationEvent::where('user_id', $student->id)->count());
        $this->assertDatabaseHas('gamification_events', [
            'user_id' => $student->id,
            'event_type' => 'lesson_completed',
            'event_key' => 'lesson:'.$lesson->id,
            'points' => 10,
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('10 XP')
            ->assertSee('First Step');
    }

    public function test_passing_exam_awards_submission_xp_and_pass_bonus_once(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $exam = Exam::create([
            'title' => 'XP Practice Exam',
            'duration_minutes' => 30,
            'total_marks' => 10,
            'pass_marks' => 5,
            'negative_marking' => 0,
            'is_free' => true,
            'is_published' => true,
        ]);
        $question = Question::create([
            'exam_id' => $exam->id,
            'type' => 'mcq',
            'question' => 'Choose the correct answer.',
            'marks' => 10,
            'position' => 1,
        ]);
        $correct = Option::create([
            'question_id' => $question->id,
            'option_text' => 'Correct answer',
            'is_correct' => true,
            'position' => 1,
        ]);
        Option::create([
            'question_id' => $question->id,
            'option_text' => 'Wrong answer',
            'is_correct' => false,
            'position' => 2,
        ]);
        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);
        AttemptAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'option_id' => $correct->id,
        ]);

        $this->actingAs($student)
            ->post(route('student.attempts.submit', $attempt), ['answers' => []])
            ->assertRedirect(route('student.attempts.result', $attempt));

        $this->assertSame('submitted', $attempt->fresh()->status);
        $this->assertSame(20, (int) $student->gamificationEvents()->sum('points'));
        $this->assertSame(1, $student->gamificationEvents()->where('event_type', 'exam_submitted')->count());
        $this->assertSame(1, $student->gamificationEvents()->where('event_type', 'exam_passed')->count());
    }

    public function test_free_preview_completion_does_not_award_course_xp_without_enrollment(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Preview Only Course',
            'slug' => 'preview-only-course',
            'price' => 500,
            'is_published' => true,
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Free Preview',
            'type' => 'text',
            'content' => 'Preview content.',
            'position' => 1,
            'is_published' => true,
            'is_free_preview' => true,
        ]);

        $this->actingAs($student)
            ->post(route('student.learn.complete', [$course->slug, $lesson->id]))
            ->assertRedirect();

        $this->assertSame(0, GamificationEvent::where('user_id', $student->id)->count());
    }
}
