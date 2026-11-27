@extends('layouts.admin')
@section('title','Complaints')
@section('heading','Complaint Management')
@section('content')
<form method="GET" class="mb-4 grid gap-2 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
    <input name="q" value="{{ request('q') }}" placeholder="Tracking ID, subject, user" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All statuses</option>@foreach(['submitted','under_review','in_progress','resolved','rejected','closed'] as $v)<option value="{{ $v }}" @selected(request('status')===$v)>{{ str_replace('_',' ',ucfirst($v)) }}</option>@endforeach</select>
    <select name="category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All categories</option>@foreach(['teacher','class','course','payment','administration','technical','other'] as $v)<option value="{{ $v }}" @selected(request('category')===$v)>{{ ucfirst($v) }}</option>@endforeach</select>
    <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-bold text-white">Filter</button>
</form>
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white"><table class="w-full min-w-[760px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-3">Tracking ID</th><th class="p-3">Complainant</th><th class="p-3">Subject</th><th class="p-3">Priority</th><th class="p-3">Status</th><th class="p-3">Assigned</th><th class="p-3"></th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($complaints as $c)<tr><td class="p-3 font-bold text-emerald-700">{{ $c->tracking_code }}</td><td class="p-3">{{ $c->user?->name ?? 'Deleted user' }}</td><td class="p-3">{{ $c->subject }}</td><td class="p-3">{{ ucfirst($c->priority) }}</td><td class="p-3">{{ str_replace('_',' ',ucfirst($c->status)) }}</td><td class="p-3">{{ $c->assignee?->name ?? '—' }}</td><td class="p-3"><a href="{{ route('admin.complaints.show',$c) }}" class="font-bold text-emerald-700">Review</a></td></tr>@empty<tr><td colspan="7" class="p-8 text-center text-slate-500">No complaints found.</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $complaints->links() }}</div>
@endsection
