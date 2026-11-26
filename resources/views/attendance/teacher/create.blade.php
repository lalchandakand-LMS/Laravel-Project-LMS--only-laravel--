@extends('layouts.portal')
@section('title','New attendance')
@section('heading','Create attendance session')
@section('content')
<div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<form method="POST" action="{{ route('teacher.attendance.store') }}" class="space-y-4">@csrf
<p class="text-sm text-slate-600">একটি মাত্র learning path নির্বাচন করুন। অনলাইন ব্যাচ ও সরাসরি কোচিং ব্যাচ আলাদা।</p>
<label class="block text-sm font-semibold">Online live-class batch<select name="batch_id" class="mt-1 w-full rounded-xl border-slate-300"><option value="">— অনলাইন ব্যাচ নয় —</option>@foreach($onlineBatches as $batch)<option value="{{ $batch->id }}" @selected(old('batch_id')==$batch->id)>{{ $batch->name }} — {{ $batch->course?->title }}</option>@endforeach</select></label>
<label class="block text-sm font-semibold">সরাসরি কোচিং ব্যাচ<select name="coaching_batch_id" class="mt-1 w-full rounded-xl border-slate-300"><option value="">— কোচিং ব্যাচ নয় —</option>@foreach($coachingBatches as $batch)<option value="{{ $batch->id }}" @selected(old('coaching_batch_id')==$batch->id)>{{ $batch->name }} — {{ $batch->course?->title }} / {{ $batch->branch?->name }}</option>@endforeach</select></label>
<label class="block text-sm font-semibold">Topic / lesson<input name="topic" value="{{ old('topic') }}" maxlength="180" class="mt-1 w-full rounded-xl border-slate-300"></label>
<label class="block text-sm font-semibold">Class date & time<input type="datetime-local" name="starts_at" value="{{ old('starts_at',now()->format('Y-m-d\TH:i')) }}" required class="mt-1 w-full rounded-xl border-slate-300"></label>
<label class="block text-sm font-semibold">End time (optional)<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
<button class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Create session</button>
</form></div>
@endsection
