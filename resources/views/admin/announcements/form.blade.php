@extends('layouts.admin')
@section('title', 'Announcement')
@section('heading', $announcement->exists ? 'Edit announcement' : 'New announcement')
@section('content')
<form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}"
      class="max-w-2xl space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($announcement->exists) @method('PUT') @endif
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Title *</label>
        <input name="title" value="{{ old('title', $announcement->title) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Course (leave empty for all students)</label>
        <select name="course_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">— all students —</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}" @selected(old('course_id', $announcement->course_id) == $course->id)>{{ $course->title }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Batch (optional)</label>
        <select name="batch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="">All batches / selected course</option>
            @foreach ($batches as $batch)<option value="{{ $batch->id }}" @selected(old('batch_id', $announcement->batch_id) == $batch->id)>{{ $batch->name }}{{ $batch->course ? ' — '.$batch->course->title : '' }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Audience</label>
        <select name="audience_role" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">All relevant users</option>
            @foreach (['student'=>'Students','guardian'=>'Guardians / Parents','instructor'=>'Teachers'] as $value=>$label)<option value="{{ $value }}" @selected(old('audience_role', $announcement->audience_role) === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="mb-1 block text-sm font-semibold text-slate-700">Priority</label><select name="priority" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">@foreach(['general'=>'General','important'=>'Important','urgent'=>'Urgent'] as $value=>$label)<option value="{{ $value }}" @selected(old('priority', $announcement->priority ?? 'general') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="flex items-end"><label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned ?? false))> Pin to top</label></div>
        <div><label class="mb-1 block text-sm font-semibold text-slate-700">Publish at</label><input type="datetime-local" name="publish_at" value="{{ old('publish_at', $announcement->publish_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
        <div><label class="mb-1 block text-sm font-semibold text-slate-700">Expires at</label><input type="datetime-local" name="expires_at" value="{{ old('expires_at', $announcement->expires_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Message *</label>
        <textarea name="body" rows="6" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('body', $announcement->body) }}</textarea>
    </div>
    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $announcement->is_published ?? true)) class="rounded text-emerald-700"> Published</label>
    @error('expires_at')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
    <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save</button>
</form>
@endsection
