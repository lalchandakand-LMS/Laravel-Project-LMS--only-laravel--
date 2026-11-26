@extends('layouts.portal')
@section('title', $reportTitle)
@section('heading', $reportTitle)
@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b p-5"><p class="text-sm text-slate-500">Only attendance approved by the administration is shown here.</p></div>
<div class="overflow-x-auto"><table class="w-full min-w-[680px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Course / Batch</th><th class="px-5 py-3">Teacher</th><th class="px-5 py-3">Mode</th><th class="px-5 py-3">Attendance</th><th class="px-5 py-3">Note</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse ($records as $record)<tr><td class="px-5 py-3">{{ $record->session?->starts_at?->format('d M Y') }}</td><td class="px-5 py-3"><b>{{ $record->session?->course?->title }}</b><p class="text-xs text-slate-500">{{ $record->session?->batchName() }} · {{ $record->session?->topic }}</p></td><td class="px-5 py-3">{{ $record->session?->instructor?->name }}</td><td class="px-5 py-3">{{ ucfirst($record->session?->mode ?? '') }}</td><td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ ucfirst($record->status) }}</span></td><td class="px-5 py-3">{{ $record->note ?: '—' }}</td></tr>
@empty<tr><td colspan="6" class="p-8 text-center text-slate-500">No approved attendance records yet.</td></tr>@endforelse
</tbody></table></div><div class="p-5">{{ $records->links() }}</div></div>
@endsection
