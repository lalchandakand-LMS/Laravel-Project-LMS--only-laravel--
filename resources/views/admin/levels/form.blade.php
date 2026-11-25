@extends('layouts.admin')
@section('title', $level->exists ? 'Edit level' : 'New level')
@section('heading', $level->exists ? 'Edit level' : 'New level')
@section('content')
<form method="POST" action="{{ $level->exists ? route('admin.levels.update', $level) : route('admin.levels.store') }}" class="max-w-2xl space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
@csrf @if($level->exists) @method('PUT') @endif
<div><label class="mb-1 block text-sm font-semibold">Level name</label><input name="name" required maxlength="100" value="{{ old('name', $level->name) }}" placeholder="e.g. Beginner" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></div>
<div><label class="mb-1 block text-sm font-semibold">Description</label><textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('description', $level->description) }}</textarea></div>
<div class="grid gap-4 sm:grid-cols-2"><div><label class="mb-1 block text-sm font-semibold">Display order</label><input type="number" min="0" name="position" value="{{ old('position', $level->position ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></div><label class="flex items-center gap-2 self-end pb-3 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $level->exists ? $level->is_active : true))> Active</label></div>
<div class="flex gap-2"><button class="rounded-lg bg-emerald-700 px-5 py-2.5 font-bold text-white">Save level</button><a href="{{ route('admin.levels.index') }}" class="rounded-lg border px-5 py-2.5">Cancel</a></div>
</form>
@endsection
