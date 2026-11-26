@extends('layouts.admin')
@section('title', 'Review Exam')
@section('heading', 'Review · '.$attempt->user?->name)
@section('content')
<div class="mb-4 grid gap-3 sm:grid-cols-3"><div class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Current score</p><p class="text-2xl font-extrabold">{{ $attempt->score }} / {{ $exam->total_marks }}</p></div><div class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Submitted</p><p class="font-bold">{{ $attempt->submitted_at?->format('d M Y, h:i A') ?? '—' }}</p></div><div class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Manual review</p><p class="font-bold">{{ $attempt->manual_reviewed_at?->format('d M Y, h:i A') ?? 'Pending' }}</p></div></div>
<form method="POST" action="{{ route('admin.exams.results.grade', [$exam,$attempt]) }}" class="space-y-4">@csrf
@foreach($attempt->answers as $answer)
<div class="rounded-2xl border border-slate-200 bg-white p-5"><div class="flex justify-between gap-4"><p class="font-semibold">{{ $answer->question?->question }}</p><span class="text-xs font-bold text-slate-500">{{ $answer->question?->marks }} marks · {{ strtoupper($answer->question?->type ?? '') }}</span></div>
<div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">{{ $answer->option?->option_text ?? $answer->answer_text ?? 'No answer submitted' }}</div>
@if(in_array($answer->question?->type,['cq','short'],true))
<label class="mt-3 block text-sm font-semibold">Award marks (maximum {{ $answer->question->marks }})</label><input type="number" step="0.25" min="0" max="{{ $answer->question->marks }}" name="marks[{{ $answer->id }}]" value="{{ old('marks.'.$answer->id,$answer->marks_awarded) }}" class="mt-1 w-40 rounded-lg border border-slate-300 px-3 py-2">
@else
<p class="mt-2 text-sm {{ $answer->is_correct ? 'text-emerald-700' : 'text-rose-700' }}">Auto-marked: {{ $answer->marks_awarded }} marks {{ $answer->is_correct ? '· Correct' : '· Incorrect' }}</p>
@endif
</div>
@endforeach
<label class="block text-sm font-semibold">Teacher feedback / notes</label><textarea name="feedback" rows="3" class="w-full rounded-lg border border-slate-300 p-3" placeholder="Feedback notes (optional)">{{ old('feedback', $attempt->manual_feedback) }}</textarea>
<button class="rounded-lg bg-orange-600 px-5 py-2.5 font-bold text-white">Save manual marks & recalculate result</button>
</form>
@endsection
