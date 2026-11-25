@extends('layouts.portal')
@section('title', 'New attendance')
@section('heading', 'Create attendance session')
@section('content')
<div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<form method="POST" action="{{ route('teacher.attendance.store') }}" class="space-y-4">@csrf
<label class="block text-sm font-semibold">Batch<select name="batch_id" required class="mt-1 w-full rounded-xl border-slate-300"><option value="">Select assigned batch</option>@foreach ($batches as $batch)<option value="{{ $batch->id }}" @selected(old('batch_id') == $batch->id)>{{ $batch->name }} — {{ $batch->course?->title }}</option>@endforeach</select></label>
<label class="block text-sm font-semibold">Class mode<select name="mode" required class="mt-1 w-full rounded-xl border-slate-300">@foreach (['online','offline','coaching'] as $mode)<option value="{{ $mode }}" @selected(old('mode','offline') === $mode)>{{ ucfirst($mode) }}</option>@endforeach</select></label>
<label class="block text-sm font-semibold">Topic / lesson<input name="topic" value="{{ old('topic') }}" maxlength="180" class="mt-1 w-full rounded-xl border-slate-300"></label>
<label class="block text-sm font-semibold">Class date & time<input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required class="mt-1 w-full rounded-xl border-slate-300"></label>
<label class="block text-sm font-semibold">End time (optional)<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
<button class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Create session</button>
</form></div>
@endsection
