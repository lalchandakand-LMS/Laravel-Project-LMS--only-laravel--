@extends('layouts.portal')
@section('title', 'Assignment submissions')
@section('heading', $assignment->title)
@section('content')
<div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5">
    <p class="text-sm text-slate-500">{{ $assignment->course?->title }} · Total marks: {{ number_format($assignment->total_marks, 2) }}</p>
    <p class="mt-1 text-sm text-slate-600">{{ $assignment->due_at ? 'Deadline: '.$assignment->due_at->format('d M Y, h:i A') : 'No deadline' }}</p>
</div>
<div class="space-y-4">
    @forelse ($submissions as $submission)
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-extrabold text-slate-900">{{ $submission->student?->name }}</h3>
                    <p class="text-xs text-slate-500">{{ $submission->student?->email }} · Submitted {{ $submission->submitted_at?->format('d M Y, h:i A') }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $submission->status === 'graded' ? 'bg-emerald-100 text-emerald-700' : ($submission->status === 'returned' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') }}">{{ ucfirst($submission->status) }}</span>
            </div>
            @if ($submission->answer_text)
                <div class="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm text-slate-700">{{ $submission->answer_text }}</div>
            @endif
            @if ($submission->attachment)
                <a href="{{ route('teacher.assignments.submission-file', [$assignment, $submission]) }}" class="mt-3 inline-flex text-sm font-bold text-indigo-700">Download student attachment ↓</a>
            @endif
            <form method="POST" action="{{ route('teacher.assignments.grade', [$assignment, $submission]) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Score / {{ number_format($assignment->total_marks, 2) }}</label>
                    <input type="number" name="score" min="0" max="{{ $assignment->total_marks }}" step="0.01" value="{{ old('score', $submission->score) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Feedback</label>
                    <textarea name="feedback" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('feedback', $submission->feedback) }}</textarea>
                </div>
                <div class="sm:col-span-3 flex flex-wrap gap-2">
                    <button name="status" value="graded" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Save grade</button>
                    <button name="status" value="returned" class="rounded-lg border border-amber-300 px-4 py-2 text-xs font-bold text-amber-800">Return for revision</button>
                </div>
                @if ($errors->any())<p class="sm:col-span-3 text-xs text-rose-600">{{ $errors->first() }}</p>@endif
            </form>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No student submissions yet.</div>
    @endforelse
</div>
<div class="mt-4">{{ $submissions->links() }}</div>
@endsection
