<?php

namespace Tests\Feature;

use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionBankItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdvancedExamBuilderTest extends TestCase
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

    private function student(): User
    {
        return User::factory()->create(['role' => 'student', 'is_active' => true]);
    }

    private function freeExam(array $attributes = []): Exam
    {
        return Exam::create(array_merge([
            'title' => 'Advanced English Exam',
            'duration_minutes' => 30,
            'total_marks' => 10,
            'pass_marks' => 5,
            'is_free' => true,
            'is_published' => true,
            'random_question_count' => 0,
        ], $attributes));
    }

    private function addMcq(Exam $exam, string $text): Question
    {
        $question = Question::create([
            'exam_id' => $exam->id,
            'type' => 'mcq',
            'question' => $text,
            'marks' => 1,
            'position' => (int) $exam->questions()->count(),
        ]);
        Option::create(['question_id' => $question->id, 'option_text' => 'Correct', 'is_correct' => true, 'position' => 0]);
        Option::create(['question_id' => $question->id, 'option_text' => 'Wrong', 'is_correct' => false, 'position' => 1]);

        return $question;
    }

    public function test_admin_can_create_update_and_delete_reusable_question_bank_items(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->post(route('admin.question-bank.store'), [
            'question' => 'Choose the correct verb.',
            'type' => 'mcq',
            'difficulty' => 'easy',
            'category' => 'Grammar',
            'marks' => 1,
            'options' => [0 => 'go', 2 => 'went'],
            'correct_option_index' => 2,
        ])->assertRedirect();

        $item = QuestionBankItem::firstOrFail();
        $this->assertSame(1, $item->correct_option_index);
        $this->actingAs($admin)->put(route('admin.question-bank.update', $item), [
            'question' => 'Updated question',
            'type' => 'short',
            'difficulty' => 'hard',
            'category' => 'Tenses',
            'marks' => 2,
        ])->assertRedirect(route('admin.question-bank.index'));
        $this->assertSame('Updated question', $item->fresh()->question);

        $this->actingAs($admin)->delete(route('admin.question-bank.destroy', $item))->assertRedirect();
        $this->assertDatabaseMissing('question_bank_items', ['id' => $item->id]);
    }

    public function test_admin_can_bulk_import_questions_from_csv(): void
    {
        $csv = "question,type,marks,options,correct_index,category,difficulty,explanation\n"
            . "\"Past tense of go?\",mcq,1,\"went|gone|goes\",0,Grammar,easy,\"Went is correct.\"\n"
            . "\"Write a sentence.\",short,2,,,Writing,medium,\"Use a complete sentence.\"\n";

        $this->actingAs($this->admin())
            ->post(route('admin.question-bank.import'), ['file' => UploadedFile::fake()->createWithContent('questions.csv', $csv)])
            ->assertRedirect();

        $this->assertDatabaseCount('question_bank_items', 2);
        $this->assertDatabaseHas('question_bank_items', ['question' => 'Past tense of go?', 'correct_option_index' => 0]);
    }

    public function test_random_question_set_is_saved_and_reused_for_an_attempt(): void
    {
        $student = $this->student();
        $exam = $this->freeExam(['random_question_count' => 2]);
        $questions = collect(range(1, 5))->map(fn ($n) => $this->addMcq($exam, 'Question '.$n));

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->where('user_id', $student->id)->firstOrFail();

        $selected = $attempt->questions()->pluck('questions.id')->all();
        $this->assertCount(2, $selected);
        $this->assertCount(2, array_unique($selected));
        $this->assertSame(2.0, (float) $attempt->max_score);
        $this->assertEqualsCanonicalizing(
            $selected,
            $attempt->fresh()->questions()->pluck('questions.id')->all()
        );
        $this->assertCount(5, $questions);
    }

    public function test_exam_start_is_blocked_when_random_pool_is_too_small(): void
    {
        $student = $this->student();
        $exam = $this->freeExam(['random_question_count' => 3]);
        $this->addMcq($exam, 'Only question');

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertStatus(422);
        $this->assertDatabaseMissing('exam_attempts', ['exam_id' => $exam->id, 'user_id' => $student->id]);
    }

    public function test_admin_can_manually_grade_cq_answers_and_recalculate_result(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $exam = $this->freeExam();
        $question = Question::create([
            'exam_id' => $exam->id, 'type' => 'cq', 'question' => 'Explain the tense.',
            'marks' => 5, 'position' => 1,
        ]);
        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id, 'started_at' => now()->subMinutes(5),
            'submitted_at' => now(), 'status' => 'submitted', 'score' => 0,
        ]);
        $answer = AttemptAnswer::create([
            'exam_attempt_id' => $attempt->id, 'question_id' => $question->id,
            'answer_text' => 'A descriptive answer.', 'marks_awarded' => 0,
        ]);

        $this->actingAs($admin)->post(route('admin.exams.results.grade', [$exam, $attempt]), [
            'marks' => [$answer->id => 4],
            'feedback' => 'Good explanation.',
        ])->assertRedirect(route('admin.exams.results.show', [$exam, $attempt]));

        $this->assertSame(4.0, (float) $attempt->fresh()->score);
        $this->assertNotNull($attempt->fresh()->manual_reviewed_at);
        $this->assertSame('Good explanation.', $attempt->fresh()->manual_feedback);
        $this->assertTrue($answer->fresh()->is_correct === false);
    }

    public function test_manual_grading_rejects_marks_above_question_maximum(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $exam = $this->freeExam();
        $question = Question::create(['exam_id' => $exam->id, 'type' => 'short', 'question' => 'Answer', 'marks' => 2]);
        $attempt = ExamAttempt::create(['exam_id' => $exam->id, 'user_id' => $student->id, 'started_at' => now(), 'submitted_at' => now(), 'status' => 'submitted']);
        $answer = AttemptAnswer::create(['exam_attempt_id' => $attempt->id, 'question_id' => $question->id, 'answer_text' => 'Answer']);

        $this->actingAs($admin)->post(route('admin.exams.results.grade', [$exam, $attempt]), [
            'marks' => [$answer->id => 3],
        ])->assertStatus(422);
    }

    public function test_admin_can_open_result_analytics_and_instructors_cannot_open_question_bank(): void
    {
        $exam = $this->freeExam();
        $this->actingAs($this->admin())->get(route('admin.exams.analytics', $exam))->assertOk();

        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $this->actingAs($teacher)->get(route('admin.question-bank.index'))->assertForbidden();
    }

    public function test_selected_question_bank_items_are_copied_into_an_exam_without_changing_the_bank(): void
    {
        $admin = $this->admin();
        $exam = $this->freeExam();
        $item = QuestionBankItem::create([
            'created_by' => $admin->id,
            'category' => 'Grammar',
            'difficulty' => 'easy',
            'type' => 'mcq',
            'question' => 'Select the past tense.',
            'options' => ['go', 'went', 'gone'],
            'correct_option_index' => 1,
            'marks' => 2,
        ]);

        $this->actingAs($admin)->post(route('admin.question-bank.attach', $exam), [
            'item_ids' => [$item->id],
        ])->assertRedirect(route('admin.exams.questions.index', $exam));

        $question = Question::where('exam_id', $exam->id)->firstOrFail();
        $this->assertSame('Select the past tense.', $question->question);
        $this->assertSame(2.0, (float) $exam->fresh()->total_marks);
        $this->assertSame(1, $question->options()->where('is_correct', true)->firstOrFail()->position);
        $this->assertDatabaseHas('question_bank_items', ['id' => $item->id]);
    }

    public function test_student_result_uses_only_the_randomized_question_snapshot(): void
    {
        $student = $this->student();
        $exam = $this->freeExam(['random_question_count' => 1]);
        $first = $this->addMcq($exam, 'Pool question one');
        $second = $this->addMcq($exam, 'Pool question two');
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->where('user_id', $student->id)->firstOrFail();
        $selectedId = $attempt->questions()->value('questions.id');
        $unselected = $selectedId === $first->id ? $second : $first;

        $this->actingAs($student)->post(route('student.attempts.submit', $attempt), ['answers' => []])->assertRedirect();
        $response = $this->actingAs($student)->get(route('student.attempts.result', $attempt))->assertOk();
        $response->assertSee($attempt->questions()->first()->question);
        $response->assertDontSee($unselected->question);
    }

}
