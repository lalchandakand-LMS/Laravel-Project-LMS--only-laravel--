@extends('layouts.admin')
@section('title', 'Levels')
@section('heading', 'Course Levels')
@section('actions')
<a href="{{ route('admin.levels.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">+ New level</a>
@endsection
@section('content')
<div class="mb-4 flex flex-wrap items-center gap-3">
    <p class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">Total levels: <b>{{ $levels->total() }}</b></p>
    <form method="GET" class="ml-auto flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="Search levels" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Search</button></form>
</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><table class="w-full text-sm">
<thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Level</th><th class="px-5 py-3">Courses</th><th class="px-5 py-3">Position</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
<tbody class="divide-y divide-slate-100">
@forelse($levels as $level)<tr><td class="px-5 py-3"><b>{{ $level->name }}</b><p class="text-xs text-slate-500">{{ $level->description }}</p></td><td class="px-5 py-3">{{ $level->courses_count }}</td><td class="px-5 py-3">{{ $level->position }}</td><td class="px-5 py-3">{{ $level->is_active ? 'Active' : 'Inactive' }}</td><td class="px-5 py-3 text-right"><a class="font-semibold text-emerald-700" href="{{ route('admin.levels.edit', $level) }}">Edit</a><form class="inline" method="POST" action="{{ route('admin.levels.destroy', $level) }}" onsubmit="return confirm('Move this level to Recycle Bin?')">@csrf @method('DELETE')<button class="ml-3 font-semibold text-rose-600">Delete</button></form></td></tr>
@empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No levels yet. Add Beginner, Elementary, Intermediate and Advanced levels.</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $levels->links() }}</div>
@endsection
