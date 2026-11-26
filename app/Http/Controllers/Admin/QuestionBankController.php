<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionBankItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionBankController extends Controller
{
    public function index(Request $request)
    {
        $items = QuestionBankItem::query()
            ->when($request->filled('q'), fn ($q) => $q->where('question', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('difficulty'), fn ($q) => $q->where('difficulty', $request->string('difficulty')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.question-bank.index', [
            'items' => $items,
            'exam' => $request->filled('exam_id') ? Exam::findOrFail($request->integer('exam_id')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'question' => 'required|string',
            'type' => 'required|in:mcq,cq,short',
            'category' => 'nullable|string|max:100',
            'difficulty' => 'required|in:easy,medium,hard',
            'marks' => 'required|numeric|min:0.25|max:1000',
            'explanation' => 'nullable|string',
            'options' => 'nullable|array',
            'options.*' => 'nullable|string|max:500',
            'correct_option_index' => 'nullable|integer|min:0|max:9',
        ]);

        $options = array_values(array_filter($data['options'] ?? [], fn ($value) => filled($value)));
        if ($data['type'] === 'mcq') {
            validator([
                'options' => $options,
                'correct_option_index' => $data['correct_option_index'] ?? null,
            ], [
                'options' => 'required|array|min:2|max:10',
                'correct_option_index' => 'required|integer|min:0',
            ])->validate();
            abort_unless(isset($options[$data['correct_option_index']]), 422, 'Select a valid correct option.');
        }

        QuestionBankItem::create([
            'created_by' => $request->user()->id,
            'category' => $data['category'] ?? null,
            'difficulty' => $data['difficulty'],
            'type' => $data['type'],
            'question' => $data['question'],
            'options' => $data['type'] === 'mcq' ? $options : null,
            'correct_option_index' => $data['type'] === 'mcq' ? $data['correct_option_index'] : null,
            'marks' => $data['marks'],
            'explanation' => $data['explanation'] ?? null,
        ]);

        return back()->with('success', 'Question saved to the question bank.');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        abort_unless($handle, 422, 'Unable to read the uploaded file.');

        $header = fgetcsv($handle);
        $required = ['question', 'type', 'marks', 'options', 'correct_index', 'category', 'difficulty', 'explanation'];
        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header ?: []);
        abort_unless(array_diff($required, $header) === [], 422, 'CSV header must contain: '.implode(', ', $required));

        $created = 0;
        $line = 1;
        DB::transaction(function () use ($handle, $header, $required, $request, &$created, &$line) {
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                $values = array_combine(array_slice($header, 0, count($row)), $row);
                $type = trim((string) ($values['type'] ?? ''));
                abort_unless(in_array($type, ['mcq', 'cq', 'short'], true), 422, "Invalid question type on CSV line {$line}.");
                $options = $type === 'mcq' ? array_values(array_filter(array_map('trim', explode('|', (string) ($values['options'] ?? ''))), fn ($v) => $v !== '')) : [];
                $correct = $values['correct_index'] === '' ? null : (int) $values['correct_index'];
                if ($type === 'mcq') {
                    abort_unless(count($options) >= 2 && $correct !== null && isset($options[$correct]), 422, "MCQ options or correct_index invalid on CSV line {$line}.");
                }

                QuestionBankItem::create([
                    'created_by' => $request->user()->id,
                    'question' => trim((string) $values['question']),
                    'type' => $type,
                    'marks' => max(0.25, (float) $values['marks']),
                    'options' => $type === 'mcq' ? $options : null,
                    'correct_option_index' => $type === 'mcq' ? $correct : null,
                    'category' => filled($values['category'] ?? null) ? trim($values['category']) : null,
                    'difficulty' => in_array(($values['difficulty'] ?? ''), ['easy', 'medium', 'hard'], true) ? $values['difficulty'] : 'medium',
                    'explanation' => $values['explanation'] ?? null,
                ]);
                $created++;
            }
        });
        fclose($handle);

        return back()->with('success', "{$created} questions imported into the bank.");
    }

    public function attach(Request $request, Exam $exam)
    {
        $ids = $request->validate(['item_ids' => 'required|array|min:1', 'item_ids.*' => 'integer|exists:question_bank_items,id'])['item_ids'];
        DB::transaction(function () use ($ids, $exam) {
            foreach (QuestionBankItem::whereIn('id', $ids)->get() as $item) {
                $question = Question::create([
                    'exam_id' => $exam->id,
                    'type' => $item->type,
                    'question' => $item->question,
                    'explanation' => $item->explanation,
                    'marks' => $item->marks,
                    'position' => (int) $exam->questions()->max('position') + 1,
                ]);
                foreach ($item->options ?? [] as $index => $optionText) {
                    Option::create([
                        'question_id' => $question->id,
                        'option_text' => $optionText,
                        'is_correct' => $index === $item->correct_option_index,
                        'position' => $index,
                    ]);
                }
            }
            $exam->update(['total_marks' => $exam->questions()->sum('marks')]);
        });

        return redirect()->route('admin.exams.questions.index', $exam)->with('success', count($ids).' bank questions added to this exam.');
    }
}
