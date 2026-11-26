@extends('layouts.admin')
@section('title',$coachingBatch->exists?'Edit Coaching Batch':'New Coaching Batch')
@section('heading',$coachingBatch->exists?'কোচিং ব্যাচ সম্পাদনা':'নতুন সরাসরি কোচিং ব্যাচ')
@section('content')
<form method="POST" action="{{ $coachingBatch->exists?route('admin.coaching-batches.update',$coachingBatch):route('admin.coaching-batches.store') }}" class="max-w-3xl space-y-4 rounded-2xl border bg-white p-6">@csrf @if($coachingBatch->exists)@method('PUT')@endif
<div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">শাখা<select name="branch_id" required class="mt-1 w-full rounded-lg border-slate-300">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id',$coachingBatch->branch_id)==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
<label class="text-sm font-semibold">কোর্স<select name="course_id" required class="mt-1 w-full rounded-lg border-slate-300">@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id',$coachingBatch->course_id)==$course->id)>{{ $course->title }}</option>@endforeach</select></label>
<label class="text-sm font-semibold">ব্যাচের নাম<input name="name" required value="{{ old('name',$coachingBatch->name) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
<label class="text-sm font-semibold">কোড<input name="code" required value="{{ old('code',$coachingBatch->code) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
<label class="text-sm font-semibold">ভর্তি ফি (৳)<input type="number" min="0" step=".01" name="admission_fee" required value="{{ old('admission_fee',$coachingBatch->admission_fee??0) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
<label class="text-sm font-semibold">মাসিক ফি (৳)<input type="number" min="0" step=".01" name="monthly_fee" required value="{{ old('monthly_fee',$coachingBatch->monthly_fee??0) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
<label class="text-sm font-semibold">শিক্ষক<select name="instructor_id" class="mt-1 w-full rounded-lg border-slate-300"><option value="">নির্বাচন করুন</option>@foreach($instructors as $teacher)<option value="{{ $teacher->id }}" @selected(old('instructor_id',$coachingBatch->instructor_id)==$teacher->id)>{{ $teacher->name }}</option>@endforeach</select></label>
<label class="text-sm font-semibold">Level<select name="level_id" class="mt-1 w-full rounded-lg border-slate-300"><option value="">—</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected(old('level_id',$coachingBatch->level_id)==$level->id)>{{ $level->name }}</option>@endforeach</select></label>
<label class="text-sm font-semibold">শুরু<input type="date" name="starts_at" value="{{ old('starts_at',$coachingBatch->starts_at?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
<label class="text-sm font-semibold">শেষ<input type="date" name="ends_at" value="{{ old('ends_at',$coachingBatch->ends_at?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
<label class="text-sm font-semibold">ক্লাসের দিন<input name="schedule_days" value="{{ old('schedule_days',$coachingBatch->schedule_days) }}" placeholder="Sat, Mon, Wed" class="mt-1 w-full rounded-lg border-slate-300"></label>
<label class="text-sm font-semibold">সময়<input name="schedule_time" value="{{ old('schedule_time',$coachingBatch->schedule_time) }}" placeholder="4:00 PM – 5:00 PM" class="mt-1 w-full rounded-lg border-slate-300"></label>
<label class="text-sm font-semibold">সর্বোচ্চ আসন<input type="number" min="1" name="capacity" value="{{ old('capacity',$coachingBatch->capacity) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
<label class="text-sm font-semibold">স্ট্যাটাস<select name="status" class="mt-1 w-full rounded-lg border-slate-300">@foreach(['upcoming','ongoing','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status',$coachingBatch->status)===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label></div>
<label class="block text-sm font-semibold">বিবরণ<textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-slate-300">{{ old('description',$coachingBatch->description) }}</textarea></label>
<div class="flex gap-3"><button class="rounded-lg bg-emerald-700 px-5 py-2.5 font-bold text-white">Save</button><a href="{{ route('admin.coaching-batches.index') }}" class="rounded-lg border px-5 py-2.5">Cancel</a></div>
</form>
@endsection
