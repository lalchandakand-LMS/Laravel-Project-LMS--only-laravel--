@extends('layouts.admin')
@section('title', 'Announcement')
@section('heading', $announcement->exists ? 'Edit announcement' : 'New announcement')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}"
      class="max-w-3xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($announcement->exists) @method('PUT') @endif
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Title *</label>
        <input name="title" value="{{ old('title', $announcement->title) }}" required maxlength="180" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        @error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Branch</label>
            <select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">All branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $announcement->branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('branch_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Audience</label>
            <select name="audience_role" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">All relevant users</option>
                @foreach (['student'=>'Students','guardian'=>'Guardians / Parents','instructor'=>'Teachers','branch_manager'=>'Branch Managers'] as $value=>$label)
                    <option value="{{ $value }}" @selected(old('audience_role', $announcement->audience_role) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Course</label>
            <select name="course_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">All courses</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected(old('course_id', $announcement->course_id) == $course->id)>{{ $course->title }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Batch</label>
            <select name="batch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">All batches / selected course</option>
                @foreach ($batches as $batch)
                    <option value="{{ $batch->id }}" @selected(old('batch_id', $announcement->batch_id) == $batch->id)>{{ $batch->name }}{{ $batch->course ? ' — '.$batch->course->title : '' }}</option>
                @endforeach
            </select>
            @error('batch_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Priority</label>
            <select name="priority" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                @foreach(['general'=>'General','important'=>'Important','urgent'=>'Urgent'] as $value=>$label)
                    <option value="{{ $value }}" @selected(old('priority', $announcement->priority ?? 'general') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned ?? false))> Pin to top</label>
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Publish at</label>
            <input type="datetime-local" name="publish_at" value="{{ old('publish_at', $announcement->publish_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Expires at</label>
            <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $announcement->expires_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            @error('expires_at')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Message *</label>
        <p class="mb-2 text-xs text-slate-500">Markdown supported: **bold**, *italic*, headings (#), bullet lists and links. HTML is not accepted.</p>
        <textarea name="body" rows="10" required maxlength="30000" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('body', $announcement->body) }}</textarea>
        @error('body')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Attachments (maximum 5 files, 10 MB each)</label>
        <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp" class="block w-full rounded-lg border border-slate-300 p-2 text-sm">
        @error('attachments.*')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        @if($announcement->exists && $announcement->attachments->isNotEmpty())
            <div class="mt-3 space-y-2 rounded-lg bg-slate-50 p-3">
                <p class="text-xs font-bold text-slate-600">Existing attachments</p>
                @foreach($announcement->attachments as $attachment)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}">
                        Remove {{ $attachment->original_name }} ({{ number_format($attachment->size / 1024, 1) }} KB)
                    </label>
                @endforeach
            </div>
        @endif
    </div>
    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $announcement->is_published ?? true)) class="rounded text-emerald-700"> Published
    </label>
    <div class="flex flex-wrap gap-3">
        <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save announcement</button>
        <a href="{{ route('admin.announcements.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
    </div>
</form>
@endsection
