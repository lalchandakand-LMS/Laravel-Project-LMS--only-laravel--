<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalExamAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createPaidExam(): array
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Exam Access Course',
            'slug' => 'exam-access-course',
            'price' => 1200,
            'is_published' => true,
        ]);
        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => 'Course Final Exam',
            'duration_minutes' => 30,
            'total_marks' => 20,
            'pass_marks' => 10,
            'negative_marking' => 0,
            'is_free' => false,
            'is_published' => true,
        ]);

        return [$student, $course, $exam];
    }

    public function test_student_without_enrollment_cannot_view_or_start_paid_exam(): void
    {
        [$student, , $exam] = $this->createPaidExam();

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();
        $this->assertDatabaseMissing('exam_attempts', [
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_expired_enrollment_cannot_view_or_start_paid_exam(): void
    {
        [$student, $course, $exam] = $this->createPaidExam();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1200,
            'enrolled_at' => now()->subDays(40),
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();
    }

    public function test_ineligible_batch_or_branch_blocks_paid_exam(): void
    {
        [$student, $course, $exam] = $this->createPaidExam();
        $assignedBranch = Branch::create(['name' => 'Exam Branch', 'code' => 'EXAM-BR', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Other Exam Branch', 'code' => 'EXAM-OTHER', 'is_active' => true]);
        $student->update(['branch_id' => $otherBranch->id]);

        $batch = Batch::create([
            'course_id' => $course->id,
            'branch_id' => $assignedBranch->id,
            'name' => 'Active Batch',
            'code' => 'EXAM-ACTIVE',
            'status' => 'ongoing',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
            'price_paid' => 1200,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();

        $student->update(['branch_id' => $assignedBranch->id]);
        $batch->update(['status' => 'upcoming']);
        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
    }

    public function test_eligible_student_can_view_and_start_paid_exam(): void
    {
        [$student, $course, $exam] = $this->createPaidExam();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1200,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(10),
        ]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertOk();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();

        $this->assertDatabaseHas('exam_attempts', [
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_free_exam_is_available_without_course_enrollment(): void
    {
        [$student, , $exam] = $this->createPaidExam();
        $exam->update(['is_free' => true]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertOk();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
    }

    public function test_unpublished_course_exam_is_not_visible_even_when_marked_free(): void
    {
        [$student, $course, $exam] = $this->createPaidExam();
        $course->update(['is_published' => false]);
        $exam->update(['is_free' => true]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();
    }

    public function test_expired_attempt_is_finalized_instead_of_accepting_late_answers(): void
    {
        [$student, , $exam] = $this->createPaidExam();
        $exam->update(['is_free' => true]);
        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now()->subMinutes(31),
            'status' => 'in_progress',
        ]);

        $this->actingAs($student)
            ->post(route('student.attempts.submit', $attempt), ['answers' => []])
            ->assertRedirect(route('student.attempts.result', $attempt))
            ->assertSessionHas('info');

        $this->assertSame('submitted', $attempt->fresh()->status);
        $this->assertNotNull($attempt->fresh()->submitted_at);
    }

    public function test_autosave_after_attempt_deadline_finalizes_attempt_and_returns_result_url(): void
    {
        [$student, , $exam] = $this->createPaidExam();
        $exam->update(['is_free' => true]);
        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now()->subMinutes(31),
            'status' => 'in_progress',
        ]);

        $this->actingAs($student)
            ->postJson(route('student.attempts.save', $attempt), ['answers' => []])
            ->assertOk()
            ->assertJsonPath('expired', true)
            ->assertJsonPath('redirect', route('student.attempts.result', $attempt));

        $this->assertSame('submitted', $attempt->fresh()->status);
    }


    public function test_student_can_resume_an_unfinished_attempt_even_when_it_uses_the_last_allowed_attempt(): void
    {
        [$student, , $exam] = $this->createPaidExam();
        $exam->update(['is_free' => true, 'attempt_limit' => 1]);

        $firstResponse = $this->actingAs($student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $student->id)
            ->where('status', 'in_progress')
            ->firstOrFail();
        $firstResponse->assertRedirect(route('student.attempts.show', $attempt));

        $this->actingAs($student)
            ->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.attempts.show', $attempt));

        $this->assertDatabaseCount('exam_attempts', 1);
    }

    public function test_completed_exam_attempt_cannot_be_changed_by_late_autosave_or_resubmission(): void
    {
        [$student, , $exam] = $this->createPaidExam();
        $exam->update(['is_free' => true, 'pass_marks' => 5]);

        $question = $exam->questions()->create([
            'type' => 'mcq',
            'question' => 'Which answer is correct?',
            'marks' => 10,
            'position' => 1,
        ]);
        $correctOption = $question->options()->create([
            'option_text' => 'Correct answer',
            'is_correct' => true,
            'position' => 1,
        ]);
        $wrongOption = $question->options()->create([
            'option_text' => 'Wrong answer',
            'is_correct' => false,
            'position' => 2,
        ]);

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now()->subMinute(),
            'duration_minutes' => 30,
            'status' => 'in_progress',
        ]);
        $attempt->questions()->attach($question->id, ['position' => 1]);

        $this->actingAs($student)->post(route('student.attempts.submit', $attempt), [
            'answers' => [$question->id => $correctOption->id],
        ])->assertRedirect(route('student.attempts.result', $attempt));

        $gradedAttempt = $attempt->fresh();
        $originalSubmittedAt = $gradedAttempt->submitted_at;
        $this->assertSame('submitted', $gradedAttempt->status);
        $this->assertSame(10.0, (float) $gradedAttempt->score);
        $this->assertSame(1, $gradedAttempt->correct_count);

        $this->actingAs($student)->postJson(route('student.attempts.save', $attempt), [
            'answers' => [$question->id => $wrongOption->id],
        ])->assertForbidden();

        $this->actingAs($student)->post(route('student.attempts.submit', $attempt), [
            'answers' => [$question->id => $wrongOption->id],
        ])->assertForbidden();

        $savedAnswer = $attempt->answers()->where('question_id', $question->id)->firstOrFail();
        $this->assertSame($correctOption->id, $savedAnswer->option_id);
        $this->assertSame(10.0, (float) $attempt->fresh()->score);
        $this->assertEquals($originalSubmittedAt, $attempt->fresh()->submitted_at);
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('gamification_events')
            ->where('user_id', $student->id)
            ->whereIn('event_type', ['exam_submitted', 'exam_passed'])
            ->count());
    }

    public function test_student_cannot_start_another_attempt_after_the_limit_is_used(): void
    {
        [$student, , $exam] = $this->createPaidExam();
        $exam->update(['is_free' => true, 'attempt_limit' => 1]);
        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinute(),
            'status' => 'submitted',
        ]);

        $this->actingAs($student)
            ->from(route('student.exams.show', $exam))
            ->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.show', $exam))
            ->assertSessionHas('info', 'You have used all attempts.');

        $this->assertDatabaseCount('exam_attempts', 1);
    }

}
