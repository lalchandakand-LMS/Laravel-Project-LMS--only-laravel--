@extends('layouts.portal')
@section('title', $assignment->exists ? 'Edit assignment' : 'Create assignment')
@section('heading', $assignment->exists ? 'Edit assignment' : 'Create assignment')
@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $assignment->exists ? route('teacher.assignments.update', $assignment) : route('teacher.assignments.store') }}"
      class="max-w-3xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($assignment->exists) @method('PUT') @endif
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Course</label>
        <select name="course_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">Select a course</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}" @selected(old('course_id', $assignment->course_id) == $course->id)>{{ $course->title }}</option>
            @endforeach
        </select>
        @error('course_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Assignment title</label>
        <input name="title" value="{{ old('title', $assignment->title) }}" required maxlength="180" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        @error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Short description</label>
        <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('description', $assignment->description) }}</textarea>
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Instructions</label>
        <textarea name="instructions" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('instructions', $assignment->instructions) }}</textarea>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Due date & time</label>
            <input type="datetime-local" name="due_at" value="{{ old('due_at', $assignment->due_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            @error('due_at')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Total marks</label>
            <input type="number" name="total_marks" min="1" max="100000" step="0.01" required value="{{ old('total_marks', $assignment->total_marks ?? 100) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Reference file (optional, max 20 MB)</label>
        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip" class="block w-full text-sm text-slate-600">
        <label class="mt-2 block text-xs font-semibold text-slate-600">Or paste a hosted resource URL</label>
        <input type="url" name="resource_url" value="{{ old('resource_url', str_starts_with((string) $assignment->attachment, 'http') ? $assignment->attachment : '') }}" placeholder="https://example.com/reference.pdf" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @if ($assignment->attachment && ! str_starts_with((string) $assignment->attachment, 'http'))
            <p class="mt-1 text-xs text-slate-500">A private uploaded reference file is already attached. Upload or link another file to replace it.</p>
        @endif
    </div>
    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $assignment->exists ? $assignment->is_published : false)) class="rounded text-emerald-700"> Publish to eligible students
    </label>
    <div class="flex gap-2">
        <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save assignment</button>
        <a href="{{ route('teacher.assignments.index') }}" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600">Cancel</a>
    </div>
</form>
@endsection
