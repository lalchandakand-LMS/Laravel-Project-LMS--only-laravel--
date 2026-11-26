<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Exam $exam)
    {
        $this->authorizeExam($exam);
        return view('admin.questions.index', [
            'exam' => $exam->load('questions.options'),
        ]);
    }

    public function create(Exam $exam)
    {
        $this->authorizeExam($exam);
        return view('admin.questions.form', ['exam' => $exam, 'question' => new Question(['type' => 'mcq', 'marks' => 1])]);
    }

    public function store(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);
        abort_if($exam->attempts()->exists(), 409, 'This exam already has student attempts. Duplicate the exam to create a new question version.');
        $this->save($request, $exam);
        $this->syncTotalMarks($exam);

        return redirect()->route('admin.exams.questions.index', $exam)->with('success', 'Question added.');
    }

    public function edit(Question $question)
    {
        $this->authorizeExam($question->exam);
        return view('admin.questions.form', [
            'exam' => $question->exam,
            'question' => $question->load('options'),
        ]);
    }

    public function update(Request $request, Question $question)
    {
        $this->authorizeExam($question->exam);
        abort_if($question->exam->attempts()->exists(), 409, 'Questions are locked after the first student attempt to preserve historical results.');
        $this->save($request, $question->exam, $question);
        $this->syncTotalMarks($question->exam);

        return redirect()->route('admin.exams.questions.index', $question->exam_id)->with('success', 'Question updated.');
    }

    public function destroy(Question $question)
    {
        $this->authorizeExam($question->exam);
        abort_if($question->exam->attempts()->exists(), 409, 'Questions cannot be deleted after student attempts exist. Duplicate the exam to make changes.');
        $exam = $question->exam;
        $question->delete();
        $this->syncTotalMarks($exam);

        return back()->with('success', 'Question deleted.');
    }

    private function authorizeExam(Exam $exam): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || $exam->course?->instructor_id === $user->id), 403);
    }

    private function save(Request $request, Exam $exam, ?Question $question = null): void
    {
        $data = $request->validate([
            'question' => 'required|string',
            'type' => 'required|in:mcq,cq,short',
            'marks' => 'required|numeric|min:0.25',
            'explanation' => 'nullable|string',
            'position' => 'nullable|integer|min:0',
            'options' => 'nullable|array',
            'options.*' => 'nullable|string|max:500',
            'correct' => 'nullable|integer',
        ]);

        $question ??= new Question(['exam_id' => $exam->id]);
        $question->fill([
            'question' => $data['question'],
            'type' => $data['type'],
            'marks' => $data['marks'],
            'explanation' => $data['explanation'] ?? null,
            'position' => $data['position'] ?? 0,
        ])->save();

        $rawOptions = $data['options'] ?? [];
        $optionKeys = array_keys(array_filter($rawOptions, fn ($value) => filled($value)));
        $options = array_values(array_intersect_key($rawOptions, array_flip($optionKeys)));

        if ($data['type'] === 'mcq') {
            validator([
                'options' => $options,
                'correct' => $data['correct'] ?? null,
            ], [
                'options' => 'required|array|min:2|max:10',
                'correct' => 'required|integer|min:0',
            ])->validate();

            $correct = array_search((int) $data['correct'], $optionKeys, true);
            abort_if($correct === false, 422, 'Select a valid correct option.');

            $question->options()->delete();
            foreach ($options as $i => $text) {
                Option::create([
                    'question_id' => $question->id,
                    'option_text' => $text,
                    'is_correct' => $i === $correct,
                    'position' => $i,
                ]);
            }
        } else {
            $question->options()->delete();
        }
    }

    private function syncTotalMarks(Exam $exam): void
    {
        $exam->update(['total_marks' => $exam->questions()->sum('marks')]);
    }
}
