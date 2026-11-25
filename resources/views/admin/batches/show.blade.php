@extends('layouts.admin')
@section('title', 'Manage batch')
@section('heading', 'Batch · '.$batch->name)
@section('actions')
<a href="{{ route('admin.batches.edit', $batch) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold">Edit batch</a>
@endsection
@section('content')
<div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Course</p><b>{{ $batch->course?->title ?? 'Not assigned' }}</b></div>
    <div class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Level</p><b>{{ $batch->level?->name ?? '—' }}</b></div>
    <div class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Branch</p><b>{{ $batch->branch?->name ?? 'Online' }}</b></div>
    <div class="rounded-xl border bg-white p-4"><p class="text-xs text-slate-500">Active students</p><b>{{ $batch->activeStudentCount() }}{{ $batch->capacity ? ' / '.$batch->capacity : '' }}</b></div>
</div>
<div class="grid gap-5 xl:grid-cols-[1fr_1.3fr]">
<section class="rounded-2xl border bg-white p-5 shadow-sm">
<h2 class="font-bold">Assign a student</h2>
@if(!$batch->course_id)<p class="mt-3 text-sm text-amber-700">Edit this batch and select a course before assigning students.</p>@else
<form method="GET" class="mt-4 flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="Find student by name / email" class="min-w-0 flex-1 rounded-lg border px-3 py-2 text-sm"><button class="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white">Search</button></form>
<form method="POST" action="{{ route('admin.batches.students.store', $batch) }}" class="mt-3 space-y-3">@csrf
<label class="block text-sm font-semibold">Student account</label><select name="student_id" required class="w-full rounded-lg border px-3 py-2.5 text-sm"><option value="">Select a student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }} — {{ $student->email }}</option>@endforeach</select>
<p class="text-xs text-slate-500">Only active student accounts are listed. A student already enrolled in this course will be linked to this batch; otherwise an active zero-fee enrollment is created by this admin action.</p>
<button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Assign student</button>
</form>
@endif
</section>
<section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
<div class="border-b px-5 py-4"><h2 class="font-bold">Batch students</h2><p class="text-xs text-slate-500">Removing a student from the batch does not cancel course access or erase progress.</p></div>
<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">Enrollment</th><th class="px-4 py-3 text-right">Action</th></tr></thead><tbody class="divide-y">
@forelse($enrollments as $enrollment)<tr><td class="px-4 py-3"><b>{{ $enrollment->user?->name ?? 'Deleted account' }}</b><p class="text-xs text-slate-500">{{ $enrollment->user?->email }}</p></td><td class="px-4 py-3">{{ ucfirst($enrollment->status) }}</td><td class="px-4 py-3 text-right"><form method="POST" action="{{ route('admin.batches.students.destroy', [$batch, $enrollment]) }}" onsubmit="return confirm('Remove this student from the batch? Course access remains.')">@csrf @method('DELETE')<button class="font-semibold text-rose-600">Remove</button></form></td></tr>
@empty<tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">No students assigned to this batch yet.</td></tr>@endforelse
</tbody></table></div><div class="p-4">{{ $enrollments->links() }}</div>
</section>
</div>
<div class="mt-5"><a href="{{ route('admin.batches.index') }}" class="text-sm font-semibold text-emerald-700">← Back to batches</a></div>
@endsection
