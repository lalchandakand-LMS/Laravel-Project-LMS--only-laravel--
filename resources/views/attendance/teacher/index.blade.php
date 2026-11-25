@extends('layouts.portal')
@section('title', 'Attendance')
@section('heading', 'Class attendance')
@section('actions')
<a href="{{ route('teacher.attendance.create') }}" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">New attendance</a>
@endsection
@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b p-5">
        <p class="font-bold text-slate-800">Attendance sessions</p>
        <form method="GET" class="flex gap-2">
            <select name="status" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option>@foreach (['draft','submitted','approved','returned'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <button class="rounded-lg border px-3 text-sm font-semibold">Filter</button>
        </form>
    </div>
    <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Class / Batch</th><th class="px-5 py-3">Date</th><th class="px-5 py-3">Mode</th><th class="px-5 py-3">Students</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100">
    @forelse ($sessions as $session)
    <tr><td class="px-5 py-3"><b>{{ $session->batch?->name }}</b><p class="text-xs text-slate-500">{{ $session->course?->title }} · {{ $session->topic }}</p></td><td class="px-5 py-3">{{ $session->starts_at->format('d M Y, h:i A') }}</td><td class="px-5 py-3">{{ ucfirst($session->mode) }}</td><td class="px-5 py-3">{{ $session->records_count ?? $session->records()->count() }}</td><td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ ucfirst($session->status) }}</span></td><td class="px-5 py-3 text-right"><a class="font-bold text-emerald-700" href="{{ route('teacher.attendance.show', $session) }}">Open →</a></td></tr>
    @empty <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No attendance sessions yet.</td></tr> @endforelse
    </tbody></table></div>
    <div class="p-5">{{ $sessions->links() }}</div>
</div>
@endsection
