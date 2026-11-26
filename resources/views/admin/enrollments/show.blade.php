@extends('layouts.admin')
@section('title', 'Enrollment Details')
@section('heading', 'Enrollment Details')
@section('actions')<a href="{{ route('admin.enrollments.edit', $enrollment) }}" class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-bold text-white">Edit enrollment</a>@endsection
@section('content')
<div class="grid gap-4 lg:grid-cols-3">
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2"><h2 class="mb-4 text-lg font-bold">Learning access</h2><div class="grid gap-4 sm:grid-cols-2">
<div><p class="text-xs text-slate-500">Student</p><p class="font-semibold">{{ $enrollment->user?->name }}</p><p class="text-sm text-slate-500">{{ $enrollment->user?->email }}</p></div>
<div><p class="text-xs text-slate-500">Course</p><p class="font-semibold">{{ $enrollment->course?->title }}</p></div>
<div><p class="text-xs text-slate-500">Branch</p><p class="font-semibold">{{ $enrollment->user?->branch?->name ?? 'Not assigned' }}</p></div>
<div><p class="text-xs text-slate-500">Batch</p><p class="font-semibold">{{ $enrollment->batch?->name ?? 'No batch assigned' }}</p></div>
<div><p class="text-xs text-slate-500">Progress</p><p class="font-semibold">{{ $enrollment->progress_percent }}%</p><div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-orange-500" style="width: {{ $enrollment->progress_percent }}%"></div></div></div>
<div><p class="text-xs text-slate-500">Status</p><p class="font-semibold">{{ ucfirst($enrollment->status) }}</p></div>
<div><p class="text-xs text-slate-500">Amount paid</p><p class="font-semibold">৳{{ number_format((float) $enrollment->price_paid, 2) }}</p></div>
<div><p class="text-xs text-slate-500">Enrolled / Expires</p><p class="font-semibold">{{ $enrollment->enrolled_at?->format('d M Y') ?? '—' }} / {{ $enrollment->expires_at?->format('d M Y') ?? 'No expiry' }}</p></div>
<div class="sm:col-span-2"><p class="text-xs text-slate-500">Admin note</p><p class="whitespace-pre-wrap">{{ $enrollment->admin_note ?: 'No note added.' }}</p></div>
</div></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-3 font-bold">Enrollment history</h2><div class="space-y-4">@forelse ($enrollment->histories as $history)<div class="border-l-2 border-orange-300 pl-3"><p class="text-sm font-semibold">{{ str($history->action)->replace('_', ' ')->title() }}</p><p class="text-xs text-slate-500">{{ $history->actor?->name ?? 'System' }} · {{ $history->created_at->format('d M Y, h:i A') }}</p>@if ($history->changes)<pre class="mt-1 whitespace-pre-wrap text-[11px] text-slate-500">{{ json_encode($history->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif</div>@empty<p class="text-sm text-slate-500">No history recorded yet.</p>@endforelse</div></section>
</div>
<div class="mt-4"><a href="{{ route('admin.enrollments.index') }}" class="text-sm font-semibold text-orange-700">← Back to enrollments</a></div>
@endsection
