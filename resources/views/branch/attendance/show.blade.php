@extends('layouts.portal')
@section('title','Attendance review')
@section('heading','Attendance — '.$session->batchName())
@section('content')
<div class="mb-4 rounded-xl border border-slate-200 bg-white p-4"><p class="font-bold">{{ $session->course?->title }}</p><p class="text-sm text-slate-500">{{ $session->instructor?->name }} · {{ $session->starts_at?->format('d M Y, h:i A') }} · {{ ucfirst($session->status) }}</p>@if($session->review_note)<p class="mt-2 text-sm text-amber-700">{{ $session->review_note }}</p>@endif</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-4">Student</th><th class="p-4">Attendance</th><th class="p-4">Teacher note</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($session->records as $record)<tr><td class="p-4"><strong>{{ $record->student?->name }}</strong><p class="text-xs text-slate-500">{{ $record->student?->email }}</p></td><td class="p-4">{{ ucfirst($record->status) }}</td><td class="p-4">{{ $record->note ?: '—' }}</td></tr>@empty<tr><td colspan="3" class="p-6 text-center text-slate-500">No attendance records submitted.</td></tr>@endforelse
</tbody></table></div>
@if($session->status==='submitted')
<form method="POST" action="{{ route('branch.attendance.review',$session) }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">@csrf<label class="min-w-64 flex-1 text-sm font-semibold">Review note<textarea name="review_note" rows="2" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Required when returning attendance"></textarea></label><button name="action" value="return" class="rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-bold text-white">Return to teacher</button><button name="action" value="approve" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Approve & publish</button></form>
@endif
<a href="{{ route('branch.attendance') }}" class="mt-4 inline-block text-sm font-semibold text-orange-700">← Back to attendance</a>
@endsection
