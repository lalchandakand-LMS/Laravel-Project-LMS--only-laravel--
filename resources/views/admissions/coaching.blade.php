@extends('layouts.app')
@section('title', 'কোচিং ভর্তি আবেদন')
@section('content')
<section class="mx-auto max-w-3xl px-4 py-12">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
        <p class="text-sm font-bold uppercase tracking-wider text-emerald-700">Online Coaching Admission</p>
        <h1 class="mt-2 text-3xl font-black text-slate-900">কোচিংয়ে ভর্তি আবেদন</h1>
        <p class="mt-2 text-sm text-slate-600">এই ফর্মটি নিয়মিত কোচিংয়ের শিক্ষার্থীদের জন্য। আবেদন যাচাই ও অনুমোদনের পর student account এবং batch enrollment তৈরি হবে।</p>
        @if ($errors->any())<div class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-700">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('coaching-admission.store') }}" class="mt-7 space-y-5">@csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">শিক্ষার্থীর নাম *<input name="student_name" value="{{ old('student_name') }}" required maxlength="160" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">ইমেইল *<input type="email" name="student_email" value="{{ old('student_email') }}" required class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">মোবাইল নম্বর *<input name="student_phone" value="{{ old('student_phone') }}" required maxlength="30" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">অভিভাবকের নাম<input name="guardian_name" value="{{ old('guardian_name') }}" maxlength="160" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">অভিভাবকের মোবাইল<input name="guardian_phone" value="{{ old('guardian_phone') }}" maxlength="30" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">অভিভাবকের ইমেইল<input type="email" name="guardian_email" value="{{ old('guardian_email') }}" class="mt-1 w-full rounded-xl border-slate-300" /></label>
            </div>
            <label class="block text-sm font-semibold text-slate-700">কোর্স ও ব্যাচ নির্বাচন *</label>
            <select name="batch_id" required class="w-full rounded-xl border-slate-300"><option value="">ব্যাচ নির্বাচন করুন</option>@foreach($batches as $batch)<option value="{{ $batch->id }}" @selected(old('batch_id')==$batch->id)>{{ $batch->name }} — {{ $batch->course?->title }} / {{ $batch->branch?->name }}</option>@endforeach</select>
            <label class="block text-sm font-semibold text-slate-700">অতিরিক্ত তথ্য<textarea name="applicant_note" rows="3" maxlength="2000" class="mt-1 w-full rounded-xl border-slate-300">{{ old('applicant_note') }}</textarea></label>
            <button class="w-full rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white hover:bg-emerald-800">ভর্তি আবেদন জমা দিন</button>
        </form>
    </div>
</section>
@endsection