@extends('layouts.portal')
@section('title', 'Branch staff')
@section('heading', 'Staff — '.$branch->name)
@section('content')
<div class="mb-4 flex justify-end"><a href="{{ route('branch.people.create', 'staff') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">+ Add staff / teacher</a></div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Designation</th><th class="px-5 py-3">Contact</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($staff as $member)
                <tr>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <img src="{{ $member->avatarUrl() }}" class="h-9 w-9 rounded-full" alt="">
                            <span class="font-semibold text-slate-800">{{ $member->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ $member->roleLabel() }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $member->designation ?: '—' }}</td>
                    <td class="px-5 py-3 text-slate-600">
                        <p>{{ $member->email }}</p>
                        <p class="text-xs text-slate-500">{{ $member->phone }}</p>
                    </td>
                    <td class="px-5 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $member->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $member->is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td class="px-5 py-3 text-right"><div class="inline-flex items-center gap-3"><a href="{{ route('branch.people.edit', $member) }}" class="font-semibold text-sky-700">Edit</a><form method="POST" action="{{ route('branch.people.destroy', $member) }}" onsubmit="return confirm('Move this staff account to Recycle Bin?')">@csrf @method('DELETE')<button class="font-semibold text-rose-600">Delete</button></form></div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No staff assigned to this branch.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
