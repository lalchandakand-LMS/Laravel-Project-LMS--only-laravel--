@extends('layouts.student')
@section('title', $assignment->title)
@section('heading', 'Assignment details')
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $assignment->course?->title }}</p>
        <h2 class="mt-1 text-2xl font-extrabold text-slate-900">{{ $assignment->title }}</h2>
        <div class="mt-3 flex flex-wrap gap-3 text-sm text-slate-500">
            <span>Total marks: {{ number_format($assignment->total_marks, 2) }}</span>
            <span>{{ $assignment->due_at ? 'Deadline: '.$assignment->due_at->format('d M Y, h:i A') : 'No deadline' }}</span>
        </div>
        @if ($assignment->description)<p class="mt-4 text-sm text-slate-700">{{ $assignment->description }}</p>@endif
        @if ($assignment->instructions)<div class="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">{{ $assignment->instructions }}</div>@endif
        @if ($assignment->attachment)
            <a href="{{ route('student.assignments.resource', $assignment) }}" class="mt-4 inline-flex text-sm font-bold text-indigo-700">Download teacher reference file ↓</a>
        @endif
    </section>

    @if ($submission)
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-extrabold text-slate-900">Your submission</h3>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $submission->status === 'graded' ? 'bg-emerald-100 text-emerald-700' : ($submission->status === 'returned' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') }}">{{ ucfirst($submission->status) }}</span>
            </div>
            <p class="mt-2 text-xs text-slate-500">Submitted {{ $submission->submitted_at?->format('d M Y, h:i A') }}</p>
            @if ($submission->answer_text)<p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $submission->answer_text }}</p>@endif
            @if ($submission->attachment)<a href="{{ route('student.assignments.submission-file', $assignment) }}" class="mt-3 inline-flex text-sm font-bold text-indigo-700">Download your submitted file ↓</a>@endif
            @if ($submission->status === 'graded')
                <div class="mt-4 rounded-xl bg-emerald-50 p-4">
                    <p class="font-bold text-emerald-900">Score: {{ number_format((float) $submission->score, 2) }} / {{ number_format($assignment->total_marks, 2) }}</p>
                    @if ($submission->feedback)<p class="mt-2 whitespace-pre-line text-sm text-emerald-900">{{ $submission->feedback }}</p>@endif
                </div>
            @elseif ($submission->status === 'returned' && $submission->feedback)
                <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900"><strong>Teacher feedback:</strong><p class="mt-1 whitespace-pre-line">{{ $submission->feedback }}</p></div>
            @endif
        </section>
    @endif

    @if (! $assignment->isClosed() && (! $submission || $submission->status === 'returned'))
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-extrabold text-slate-900">{{ $submission ? 'Revise and resubmit' : 'Submit your work' }}</h3>
            <form method="POST" enctype="multipart/form-data" action="{{ route('student.assignments.submit', $assignment) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Your answer</label>
                    <textarea name="answer_text" rows="7" maxlength="20000" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" placeholder="Write your answer here...">{{ old('answer_text', $submission?->answer_text) }}</textarea>
                    @error('answer_text')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Attach a file (optional, max 20 MB)</label>
                    <input type="file" name="attachment" class="block w-full text-sm text-slate-600">
                    @if ($submission?->attachment)<p class="mt-1 text-xs text-slate-500">Your previous attachment will remain unless you upload a replacement.</p>@endif
                    @error('attachment')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Submit assignment</button>
            </form>
        </section>
    @elseif ($assignment->isClosed() && ! $submission)
        <p class="rounded-xl bg-amber-50 p-4 text-sm font-semibold text-amber-800">The submission deadline has passed.</p>
    @endif
</div>
@endsection
