@extends('layouts.portal')
@section('title','Branch attendance')
@section('heading','Attendance review — '.$branch->name)
@section('content')
<div class="mb-4 flex flex-wrap gap-2"><form method="GET" class="flex gap-2"><select name="status" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option>@foreach(['submitted','approved','returned','draft'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select><button class="rounded-lg border px-3 py-2 text-sm font-semibold">Filter</button></form></div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-4">Batch / Course</th><th class="p-4">Teacher</th><th class="p-4">Date</th><th class="p-4">Mode</th><th class="p-4">Attendance</th><th class="p-4">Status</th><th></th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($sessions as $session)<tr><td class="p-4"><strong>{{ $session->batchName() ?: 'Course session' }}</strong><p class="text-xs text-slate-500">{{ $session->course?->title }}</p></td><td class="p-4">{{ $session->instructor?->name }}</td><td class="p-4">{{ $session->starts_at?->format('d M Y, h:i A') }}</td><td class="p-4">{{ ucfirst($session->mode) }}</td><td class="p-4">{{ $session->records_count }}</td><td class="p-4">{{ ucfirst($session->status) }}</td><td class="p-4 text-right"><a href="{{ route('branch.attendance.show',$session) }}" class="font-bold text-orange-700">Review →</a></td></tr>
@empty<tr><td colspan="7" class="p-8 text-center text-slate-500">No attendance sessions for this branch.</td></tr>@endforelse
</tbody></table></div><div class="border-t p-4">{{ $sessions->links() }}</div></div>
@endsection
