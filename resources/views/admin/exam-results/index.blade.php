@extends('layouts.admin')
@section('title', 'Exam Results')
@section('heading', 'Results · '.$exam->title)
@section('actions')<a href="{{ route('admin.exams.analytics', $exam) }}" class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-bold text-white">View Analytics</a>@endsection
@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">Submitted</th><th class="px-4 py-3">Answers</th><th class="px-4 py-3">Score</th><th class="px-4 py-3">Review</th><th class="px-4 py-3">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($attempts as $attempt)<tr><td class="px-4 py-3 font-semibold">{{ $attempt->user?->name ?? 'Deleted student' }}</td><td class="px-4 py-3">{{ $attempt->submitted_at?->format('d M Y, h:i A') ?? '—' }}</td><td class="px-4 py-3">{{ $attempt->answers_count }}</td><td class="px-4 py-3 font-bold">{{ $attempt->score }} / {{ $attempt->max_score ?? $exam->total_marks }}</td><td class="px-4 py-3">{{ $attempt->manual_reviewed_at ? 'Reviewed' : ($attempt->has_manual_answers ? 'Pending manual review' : 'Auto-marked') }}</td><td class="px-4 py-3"><a class="font-semibold text-orange-700" href="{{ route('admin.exams.results.show', [$exam,$attempt]) }}">Review result</a></td></tr>
@empty<tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No submitted attempts yet.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-4">{{ $attempts->links() }}</div>
@endsection
