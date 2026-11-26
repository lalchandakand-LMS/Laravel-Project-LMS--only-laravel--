@extends('layouts.student')
@section('title', 'Assignments')
@section('heading', 'My assignments')
@section('content')
<div class="mb-5">
    <h2 class="text-xl font-extrabold text-slate-900">Course assignments</h2>
    <p class="mt-1 text-sm text-slate-500">Assignments for your active course enrollments.</p>
</div>
<div class="grid gap-4 xl:grid-cols-2">
    @forelse ($assignments as $assignment)
        @php($submission = $assignment->submissions->first())
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $assignment->course?->title }}</p>
            <h3 class="mt-1 text-lg font-extrabold text-slate-900">{{ $assignment->title }}</h3>
            @if ($assignment->description)<p class="mt-2 text-sm text-slate-600">{{ $assignment->description }}</p>@endif
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span>{{ number_format($assignment->total_marks, 2) }} marks</span>
                <span>·</span>
                <span>{{ $assignment->due_at ? 'Due '.$assignment->due_at->format('d M Y, h:i A') : 'No deadline' }}</span>
            </div>
            <div class="mt-4 flex items-center justify-between">
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $submission?->status === 'graded' ? 'bg-emerald-100 text-emerald-700' : ($submission?->status === 'returned' ? 'bg-amber-100 text-amber-700' : ($submission ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600')) }}">{{ $submission ? ucfirst($submission->status) : ($assignment->isClosed() ? 'Closed' : 'Not submitted') }}</span>
                <a href="{{ route('student.assignments.show', $assignment) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white">Open assignment →</a>
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 xl:col-span-2">No assignments are available for your enrolled courses yet.</div>
    @endforelse
</div>
@if ($assignments->hasPages())<div class="mt-5">{{ $assignments->links() }}</div>@endif
@endsection
