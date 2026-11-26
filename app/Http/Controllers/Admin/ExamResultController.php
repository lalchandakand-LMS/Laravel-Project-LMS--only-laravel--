<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamResultController extends Controller
{
    public function index(Exam $exam)
    {
        $this->authorizeExam($exam);
        $attempts = $exam->attempts()
            ->with('user')
            ->withCount('answers')
            ->whereIn('status', ['submitted', 'expired'])
            ->latest('submitted_at')
            ->paginate(25);

        return view('admin.exam-results.index', compact('exam', 'attempts'));
    }

    public function show(Exam $exam, ExamAttempt $attempt)
    {
        $this->authorizeExam($exam);
        abort_unless($attempt->exam_id === $exam->id, 404);
        $attempt->load(['user', 'answers.question.options', 'answers.option']);

        return view('admin.exam-results.show', compact('exam', 'attempt'));
    }

    public function grade(Request $request, Exam $exam, ExamAttempt $attempt)
    {
        $this->authorizeExam($exam);
        abort_unless($attempt->exam_id === $exam->id && in_array($attempt->status, ['submitted', 'expired'], true), 404);

        $data = $request->validate([
            'marks' => 'required|array',
            'marks.*' => 'required|numeric|min:0',
            'feedback' => 'nullable|string|max:5000',
        ]);

        DB::transaction(function () use ($data, $attempt) {
            $answers = $attempt->answers()->with('question')->get();
            foreach ($answers as $answer) {
                if (! in_array($answer->question->type, ['cq', 'short'], true)) {
                    continue;
                }
                $mark = (float) ($data['marks'][$answer->id] ?? 0);
                abort_if($mark > (float) $answer->question->marks, 422, 'Awarded marks cannot exceed the question maximum.');
                $answer->update([
                    'marks_awarded' => $mark,
                    'is_correct' => $mark >= (float) $answer->question->marks,
                ]);
            }

            $score = (float) $attempt->answers()->sum('marks_awarded');
            $attempt->update([
                'score' => max(0, round($score, 2)),
                'manual_reviewed_at' => now(),
                'manual_feedback' => $data['feedback'] ?? null,
            ]);
        });

        return redirect()->route('admin.exams.results.show', [$exam, $attempt])
            ->with('success', 'Manual marks saved and result recalculated.');
    }

    public function analytics(Exam $exam)
    {
        $this->authorizeExam($exam);
        $attempts = $exam->attempts()->whereIn('status', ['submitted', 'expired']);
        $total = (clone $attempts)->count();
        $passed = (clone $attempts)->get()->filter(fn ($attempt) => $attempt->isPassed())->count();
        $average = (float) (clone $attempts)->avg('score');
        $uniqueStudents = (clone $attempts)->distinct('user_id')->count('user_id');

        $questionStats = $exam->questions()->get()->map(function ($question) use ($exam) {
            $answers = \App\Models\AttemptAnswer::where('question_id', $question->id)
                ->whereHas('attempt', fn ($query) => $query->where('exam_id', $exam->id)->whereIn('status', ['submitted', 'expired']))
                ->get();
            return [
                'question' => $question,
                'responses' => $answers->count(),
                'correct' => $answers->where('is_correct', true)->count(),
                'average_marks' => $answers->count() ? round($answers->avg('marks_awarded'), 2) : 0,
            ];
        });

        $distribution = [
            '0–39%' => 0,
            '40–59%' => 0,
            '60–79%' => 0,
            '80–100%' => 0,
        ];
        foreach ((clone $attempts)->get() as $attempt) {
            $maxScore = $attempt->max_score ?? $exam->total_marks;
            $percent = $maxScore > 0 ? ($attempt->score / $maxScore) * 100 : 0;
            $bucket = $percent < 40 ? '0–39%' : ($percent < 60 ? '40–59%' : ($percent < 80 ? '60–79%' : '80–100%'));
            $distribution[$bucket]++;
        }

        return view('admin.exam-results.analytics', compact(
            'exam', 'total', 'passed', 'average', 'uniqueStudents', 'questionStats', 'distribution'
        ));
    }

    private function authorizeExam(Exam $exam): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || $exam->course?->instructor_id === $user->id), 403);
    }
}
