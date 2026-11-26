<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    /**
     * Keep exam discovery, details and starting aligned with the course-player
     * entitlement rules. An active enrollment alone is not enough: it must
     * still be unexpired and its assigned batch/branch must remain eligible.
     */
    private function canAccessExam(User $user, Exam $exam): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isStudent() || ! $exam->is_published) {
            return false;
        }

        $course = $exam->course;
        if ($exam->course_id && (! $course || ! $course->is_published)) {
            return false;
        }

        if ($exam->is_free) {
            return true;
        }

        if (! $course) {
            return false;
        }

        return \App\Services\EnrollmentEligibility::hasCourse($user, $course);
    }

    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isStudent() || $user->isAdmin(), 403);

        $exams = Exam::with('course')
            ->where('is_published', true)
            ->latest()
            ->get()
            ->filter(fn (Exam $exam) => $this->canAccessExam($user, $exam))
            ->values();

        return view('student.exams.index', ['exams' => $exams]);
    }

    public function show(Exam $exam)
    {
        $user = auth()->user();
        abort_unless($this->canAccessExam($user, $exam), 403, 'You do not have access to this exam.');

        return view('student.exams.show', [
            'exam' => $exam->load('course'),
            'attempts' => $exam->attempts()->where('user_id', $user->id)->latest()->get(),
            'best' => $exam->bestScoreFor($user),
            'left' => $exam->attemptsLeftFor($user),
        ]);
    }

    public function start(Request $request, Exam $exam)
    {
        $user = $request->user();
        abort_unless($this->canAccessExam($user, $exam) && $exam->isOpen(), 403, 'This exam is not available to your account.');

        $left = $exam->attemptsLeftFor($user);
        if ($left !== null && $left <= 0) {
            return redirect()->route('student.exams.show', $exam)->with('info', 'You have used all attempts.');
        }

        // Resume an unfinished attempt instead of creating a new one.
        $attempt = $exam->attempts()->where('user_id', $user->id)->where('status', 'in_progress')->latest()->first()
            ?? $exam->attempts()->create([
                'user_id' => $user->id,
                'started_at' => now(),
                'status' => 'in_progress',
            ]);

        return redirect()->route('student.attempts.show', $attempt);
    }

    public function attempt(ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('student.attempts.result', $attempt);
        }

        if ($attempt->remainingSeconds() <= 0) {
            $this->grade($attempt);
            return redirect()->route('student.attempts.result', $attempt)->with('info', 'Time is up — your answers were submitted.');
        }

        $questions = $attempt->exam->questions()->with('options')->get();
        if ($attempt->exam->shuffle_questions) {
            $questions = $questions->shuffle();
        }

        return view('student.exams.attempt', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'questions' => $questions,
            'saved' => $attempt->answers()->get()->keyBy('question_id'),
            'remaining' => $attempt->remainingSeconds(),
        ]);
    }

    /** Store the answer sheet (called via fetch on autosave / final submit). */
    public function save(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        abort_if($attempt->status !== 'in_progress', 403);

        if ($attempt->remainingSeconds() <= 0) {
            $this->grade($attempt);

            return response()->json([
                'ok' => true,
                'expired' => true,
                'redirect' => route('student.attempts.result', $attempt),
            ]);
        }

        $answers = (array) $request->input('answers', []);
        foreach ($attempt->exam->questions as $question) {
            $value = $answers[$question->id] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if ($question->type === 'mcq') {
                AttemptAnswer::updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
                    ['option_id' => (int) $value]
                );
            } else {
                AttemptAnswer::updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
                    ['answer_text' => (string) $value]
                );
            }
        }

        if ($request->boolean('submit')) {
            $this->grade($attempt);
            return response()->json(['ok' => true, 'redirect' => route('student.attempts.result', $attempt)]);
        }

        return response()->json(['ok' => true, 'saved_at' => now()->format('H:i:s')]);
    }

    public function submit(Request $request, ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        if ($attempt->status === 'in_progress' && $attempt->remainingSeconds() <= 0) {
            $this->grade($attempt);

            return redirect()->route('student.attempts.result', $attempt)
                ->with('info', 'Time is up — your saved answers were submitted.');
        }

        abort_if($attempt->status !== 'in_progress', 403);

        $answers = (array) $request->input('answers', []);
        foreach ($attempt->exam->questions as $question) {
            $value = $answers[$question->id] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if ($question->type === 'mcq') {
                AttemptAnswer::updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
                    ['option_id' => (int) $value]
                );
            } else {
                AttemptAnswer::updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
                    ['answer_text' => (string) $value]
                );
            }
        }

        $this->grade($attempt);

        return redirect()->route('student.attempts.result', $attempt);
    }

    public function result(ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        return view('student.exams.result', [
            'attempt' => $attempt->load('exam.course'),
            'exam' => $attempt->exam,
            'answers' => $attempt->answers()->with(['question.options', 'option'])->get()->keyBy('question_id'),
        ]);
    }

    private function authorizeAttempt(ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === auth()->id() || auth()->user()->isAdmin(), 403);
    }

    private function grade(ExamAttempt $attempt): void
    {
        if ($attempt->status !== 'in_progress') {
            return;
        }

        $score = 0.0;
        $correct = 0;
        $wrong = 0;

        foreach ($attempt->exam->questions()->with('options')->get() as $question) {
            $answer = $attempt->answers()->where('question_id', $question->id)->first();

            if ($question->type === 'mcq') {
                $correctOption = $question->options->firstWhere('is_correct', true);
                $isCorrect = $answer && $correctOption && (int) $answer->option_id === (int) $correctOption->id;

                $awarded = 0.0;
                if ($isCorrect) {
                    $awarded = (float) $question->marks;
                    $correct++;
                } elseif ($answer && $answer->option_id) {
                    $awarded = -1 * (float) $attempt->exam->negative_marking;
                    $wrong++;
                }

                $score += $awarded;

                if ($answer) {
                    $answer->update(['is_correct' => $isCorrect, 'marks_awarded' => $awarded]);
                }
            } else {
                // CQ / short answers need manual marking by the instructor.
                if ($answer) {
                    $answer->update(['is_correct' => false, 'marks_awarded' => 0]);
                }
            }
        }

        $attempt->update([
            'score' => max(0, round($score, 2)),
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
    }
}
