@extends('layouts.portal')
@section('title', 'Assignments')
@section('heading', 'Course assignments')
@section('actions')
    <a href="{{ route('teacher.assignments.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">+ Create assignment</a>
@endsection
@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-5 py-3">Assignment</th><th class="px-5 py-3">Course</th><th class="px-5 py-3">Due date</th><th class="px-5 py-3">Submissions</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($assignments as $assignment)
                <tr>
                    <td class="px-5 py-3 font-semibold text-slate-800">{{ $assignment->title }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $assignment->course?->title }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $assignment->due_at?->format('d M Y, h:i A') ?? 'No deadline' }}</td>
                    <td class="px-5 py-3">{{ $assignment->submissions_count }}</td>
                    <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $assignment->is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $assignment->is_published ? 'Published' : 'Draft' }}</span></td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('teacher.assignments.submissions', $assignment) }}" class="font-bold text-indigo-700">Submissions</a>
                        <a href="{{ route('teacher.assignments.edit', $assignment) }}" class="ml-3 font-bold text-emerald-700">Edit</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No assignments yet. Create one for a course you teach.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $assignments->links() }}</div>
@endsection
