<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        $attempt = DB::transaction(function () use ($user, $exam) {
            // Serialize starts for this exam so a rapid double-click cannot
            // create multiple attempts or bypass the configured attempt limit.
            $lockedExam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canAccessExam($user, $lockedExam) && $lockedExam->isOpen(), 403,
                'This exam is not available to your account.');

            $questionPoolCount = $lockedExam->questions()->count();
            if ($lockedExam->random_question_count > $questionPoolCount) {
                abort(422, 'This exam does not have enough questions for its configured random set.');
            }

            // An unfinished attempt is resumable even when it has consumed the
            // final allowed attempt; check for it before enforcing the limit.
            $attempt = $lockedExam->attempts()
                ->where('user_id', $user->id)
                ->where('status', 'in_progress')
                ->latest()
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                $left = $lockedExam->attemptsLeftFor($user);
                if ($left !== null && $left <= 0) {
                    return null;
                }

                $attempt = $lockedExam->attempts()->create([
                    'user_id' => $user->id,
                    'started_at' => now(),
                    'duration_minutes' => $lockedExam->duration_minutes,
                    'negative_marking' => $lockedExam->negative_marking,
                    'status' => 'in_progress',
                ]);
            }

            $this->prepareAttemptQuestions($attempt);

            return $attempt;
        });

        if (! $attempt) {
            return redirect()->route('student.exams.show', $exam)->with('info', 'You have used all attempts.');
        }

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

        $questions = $this->attemptQuestions($attempt);

        return view('student.exams.attempt', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'questions' => $questions,
            'saved' => $attempt->answers()->get()->keyBy('question_id'),
            'remaining' => $attempt->remainingSeconds(),
        ]);
    }


    /**
     * Snapshot a randomized question set on first attempt access. The same
     * question order is reused for refreshes, autosaves and grading.
     */
    private function prepareAttemptQuestions(ExamAttempt $attempt): void
    {
        if ($attempt->questions()->exists()) {
            $updates = [];
            if ($attempt->max_score === null) {
                $updates['max_score'] = $attempt->questions()->sum('marks');
            }
            if ($attempt->duration_minutes === null) {
                $updates['duration_minutes'] = $attempt->exam->duration_minutes;
            }
            if ($attempt->negative_marking === null) {
                $updates['negative_marking'] = $attempt->exam->negative_marking;
            }
            if ($attempt->pass_mark_target === null) {
                $maxScore = $updates['max_score'] ?? $attempt->max_score;
                $exam = $attempt->exam;
                $updates['pass_mark_target'] = $exam->total_marks > 0
                    ? round(($exam->pass_marks / $exam->total_marks) * $maxScore, 2)
                    : $exam->pass_marks;
            }
            if ($updates) {
                $attempt->update($updates);
            }
            return;
        }

        $pool = $attempt->exam->questions()->pluck('questions.id');
        $count = (int) $attempt->exam->random_question_count;
        if ($count > 0) {
            abort_if($pool->count() < $count, 422, 'This exam does not have enough questions for its configured random set.');
            $pool = $pool->shuffle()->take($count)->values();
        } elseif ($attempt->exam->shuffle_questions) {
            $pool = $pool->shuffle()->values();
        }

        $maxScore = \App\Models\Question::whereIn('id', $pool)->sum('marks');
        $exam = $attempt->exam;
        $attempt->update([
            'max_score' => $maxScore,
            'pass_mark_target' => $exam->total_marks > 0
                ? round(($exam->pass_marks / $exam->total_marks) * $maxScore, 2)
                : $exam->pass_marks,
            'duration_minutes' => $attempt->duration_minutes ?? $exam->duration_minutes,
            'negative_marking' => $attempt->negative_marking ?? $exam->negative_marking,
        ]);
        $sync = [];
        foreach ($pool as $position => $questionId) {
            $sync[$questionId] = ['position' => $position];
        }
        $attempt->questions()->sync($sync);
    }

    private function attemptQuestions(ExamAttempt $attempt)
    {
        $this->prepareAttemptQuestions($attempt);

        return $attempt->questions()->with('options')->get();
    }

    /**
     * Persist only answers for this attempt's frozen question set. MCQ option
     * IDs must belong to the question being answered; descriptive input is
     * bounded to keep oversized payloads out of the answer store.
     */
    private function persistAnswers(Request $request, ExamAttempt $attempt, $questions): bool
    {
        $data = $request->validate([
            'answers' => ['sometimes', 'array', 'max:'.$questions->count()],
        ]);
        $answers = $data['answers'] ?? [];

        return DB::transaction(function () use ($answers, $questions, $attempt) {
            // Autosave and final submit share this lock with grading. A request
            // that waited behind a completed submit must never overwrite answers.
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($lockedAttempt->status !== 'in_progress' || $lockedAttempt->remainingSeconds() <= 0) {
                return false;
            }

            foreach ($questions as $question) {
                if (! array_key_exists($question->id, $answers)) {
                    continue;
                }

                $value = $answers[$question->id];
                if ($value === null || $value === '') {
                    continue;
                }

                $attributes = ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id];

                if ($question->type === 'mcq') {
                    $validated = validator(['option_id' => $value], [
                        'option_id' => [
                            'required',
                            'integer',
                            Rule::exists('options', 'id')->where('question_id', $question->id),
                        ],
                    ])->validate();

                    AttemptAnswer::updateOrCreate($attributes, [
                        'option_id' => (int) $validated['option_id'],
                        'answer_text' => null,
                    ]);
                    continue;
                }

                $validated = validator(['answer_text' => $value], [
                    'answer_text' => ['required', 'string', 'max:20000'],
                ])->validate();

                AttemptAnswer::updateOrCreate($attributes, [
                    'option_id' => null,
                    'answer_text' => $validated['answer_text'],
                ]);
            }

            return true;
        });
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

        $questions = $this->attemptQuestions($attempt);
        $answersSaved = $this->persistAnswers($request, $attempt, $questions);
        if (! $answersSaved) {
            $this->grade($attempt);

            return response()->json([
                'ok' => true,
                'redirect' => route('student.attempts.result', $attempt),
            ]);
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

        $questions = $this->attemptQuestions($attempt);
        $this->persistAnswers($request, $attempt, $questions);

        // The attempt may have been finalized by a concurrent request while
        // this request was preparing its answer payload. grade() rechecks the
        // locked status and is therefore safe to call more than once.
        $this->grade($attempt);

        return redirect()->route('student.attempts.result', $attempt);
    }

    public function result(ExamAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        $this->prepareAttemptQuestions($attempt);

        return view('student.exams.result', [
            'attempt' => $attempt->load('exam.course', 'questions.options'),
            'exam' => $attempt->exam,
            'manualPending' => $attempt->manual_reviewed_at === null && $attempt->questions->contains(fn ($question) => in_array($question->type, ['cq', 'short'], true)),
            'answers' => $attempt->answers()->with(['question.options', 'option'])->get()->keyBy('question_id'),
        ]);
    }

    private function authorizeAttempt(ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === auth()->id() || auth()->user()->isAdmin(), 403);
    }

    private function grade(ExamAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt) {
            // Final scoring, answer-row updates, status transition and awards
            // are one atomic operation. Competing submit/timeout requests wait
            // here and then observe the already-finalized attempt.
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($lockedAttempt->status !== 'in_progress') {
                return;
            }

            $attempt = $lockedAttempt;
            $score = 0.0;
            $correct = 0;
            $wrong = 0;

            foreach ($this->attemptQuestions($attempt) as $question) {
                $answer = $attempt->answers()->where('question_id', $question->id)->first();

                if ($question->type === 'mcq') {
                    $correctOption = $question->options->firstWhere('is_correct', true);
                    $isCorrect = $answer && $correctOption && (int) $answer->option_id === (int) $correctOption->id;

                    $awarded = 0.0;
                    if ($isCorrect) {
                        $awarded = (float) $question->marks;
                        $correct++;
                    } elseif ($answer && $answer->option_id) {
                        $awarded = -1 * (float) ($attempt->negative_marking ?? $attempt->exam->negative_marking);
                        $wrong++;
                    }

                    $score += $awarded;

                    if ($answer) {
                        $answer->update(['is_correct' => $isCorrect, 'marks_awarded' => $awarded]);
                    }
                } else {
                    // Keep an explicit empty answer row so the marker can review unanswered CQ / short questions.
                    $answer ??= $attempt->answers()->create(['question_id' => $question->id, 'answer_text' => null]);
                    $answer->update(['is_correct' => false, 'marks_awarded' => 0]);
                }
            }

            $attempt->update([
                'score' => max(0, round($score, 2)),
                'correct_count' => $correct,
                'wrong_count' => $wrong,
                'submitted_at' => now(),
                'status' => 'submitted',
            ]);

            $attempt->refresh();
            $student = $attempt->user;
            if ($student && $student->isStudent()) {
                GamificationService::award(
                    $student,
                    'exam_submitted',
                    'exam_attempt:'.$attempt->id,
                    15,
                    ['exam_id' => $attempt->exam_id, 'attempt_id' => $attempt->id]
                );

                $requiresManualReview = $attempt->questions()->whereIn('type', ['cq', 'short'])->exists();
                if (! $requiresManualReview && $attempt->isPassed()) {
                    GamificationService::award(
                        $student,
                        'exam_passed',
                        'exam_passed:'.$attempt->id,
                        5,
                        ['exam_id' => $attempt->exam_id, 'attempt_id' => $attempt->id]
                    );
                }
            }
        });
    }
}
