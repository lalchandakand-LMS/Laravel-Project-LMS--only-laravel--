@extends('layouts.portal')
@section('title', 'Take attendance')
@section('heading', 'Take class attendance')
@section('content')
<div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5"><div class="flex flex-wrap justify-between gap-3"><div><h2 class="font-bold text-slate-900">{{ $session->batch?->name }} — {{ $session->course?->title }}</h2><p class="mt-1 text-sm text-slate-500">{{ $session->starts_at->format('d M Y, h:i A') }} · {{ ucfirst($session->mode) }} · {{ $session->topic }}</p></div><span class="h-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ ucfirst($session->status) }}</span></div>
@if ($session->review_note)<p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Admin note: {{ $session->review_note }}</p>@endif
</div>
@if (in_array($session->status, ['draft','returned'], true))
<form method="POST" action="{{ route('teacher.attendance.save', $session) }}" class="rounded-2xl border border-slate-200 bg-white shadow-sm">@csrf
<div class="flex flex-wrap items-center justify-between gap-3 border-b p-5"><h3 class="font-bold">Students ({{ $students->count() }})</h3><span class="text-xs text-slate-500">Mark each student before submitting.</span></div>
<div class="divide-y divide-slate-100">@forelse ($students as $student)
@php $record = $marked->get($student->id); @endphp
<div class="grid gap-3 p-4 sm:grid-cols-[1fr_180px_1fr] sm:items-center"><div><p class="font-semibold text-slate-800">{{ $student->name }}</p><p class="text-xs text-slate-500">{{ $student->phone ?: $student->email }}</p></div>
<select name="attendance[{{ $student->id }}][status]" required class="rounded-lg border-slate-300 text-sm"><option value="">Select status</option>@foreach (['present','absent','late','leave'] as $status)<option value="{{ $status }}" @selected(old("attendance.{$student->id}.status", $record?->status) === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
<input name="attendance[{{ $student->id }}][note]" value="{{ old("attendance.{$student->id}.note", $record?->note) }}" maxlength="500" placeholder="Optional note" class="rounded-lg border-slate-300 text-sm"></div>
@empty <p class="p-8 text-center text-slate-500">No active students are enrolled in this batch.</p> @endforelse</div>
<div class="flex flex-wrap gap-3 border-t p-5"><button class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold">Save draft</button></form>
<form method="POST" action="{{ route('teacher.attendance.submit', $session) }}">@csrf<button class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Submit to admin</button></form></div>
@else
<div class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-3 font-bold">Submitted attendance</h3><div class="divide-y">@foreach ($session->records as $record)<div class="flex justify-between py-2 text-sm"><span>{{ $record->student?->name }}</span><b>{{ ucfirst($record->status) }}</b></div>@endforeach</div></div>
@endif
@endsection
