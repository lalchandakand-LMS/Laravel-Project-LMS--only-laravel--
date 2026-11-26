@extends('layouts.app')
@section('title', 'কোচিং ভর্তি আবেদন')
@section('content')
<section class="mx-auto max-w-3xl px-4 py-12">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
        <p class="text-sm font-bold uppercase tracking-wider text-emerald-700">Online Coaching Admission</p>
        <h1 class="mt-2 text-3xl font-black text-slate-900">কোচিংয়ে ভর্তি আবেদন</h1>
        <p class="mt-2 text-sm text-slate-600">এই ফর্মটি নিয়মিত কোচিংয়ের শিক্ষার্থীদের জন্য। আবেদন যাচাই ও অনুমোদনের পর আবেদন অনুমোদনের পর নির্ধারিত ভর্তি ফি invoice হবে। ভর্তি ফি পরিশোধ হলে সরাসরি কোচিং ব্যাচ সক্রিয় হবে।</p>
        @if ($errors->any())<div class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-700">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('coaching-admission.store') }}" class="mt-7 space-y-5">@csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">শিক্ষার্থীর নাম *<input name="student_name" value="{{ old('student_name') }}" required maxlength="160" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">ইমেইল *<input type="email" name="student_email" value="{{ old('student_email') }}" required class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">মোবাইল নম্বর *<input name="student_phone" value="{{ old('student_phone') }}" required maxlength="30" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">অভিভাবকের নাম<input name="guardian_name" value="{{ old('guardian_name') }}" maxlength="160" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">অভিভাবকের মোবাইল<input name="guardian_phone" value="{{ old('guardian_phone') }}" maxlength="30" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">অভিভাবকের ইমেইল<input type="email" name="guardian_email" value="{{ old('guardian_email') }}" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">জন্মতারিখ<input type="date" name="student_date_of_birth" value="{{ old('student_date_of_birth') }}" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">লিঙ্গ<select name="student_gender" class="mt-1 w-full rounded-xl border-slate-300"><option value="">নির্বাচন করুন</option>@foreach(['male'=>'পুরুষ','female'=>'নারী','other'=>'অন্যান্য'] as $v=>$label)<option value="{{ $v }}" @selected(old('student_gender')===$v)>{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm font-semibold text-slate-700">স্কুল / কলেজ<input name="school_name" value="{{ old('school_name') }}" maxlength="180" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">শ্রেণি / লেভেল<input name="academic_class" value="{{ old('academic_class') }}" maxlength="80" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">রোল নম্বর<input name="academic_roll" value="{{ old('academic_roll') }}" maxlength="60" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">শিক্ষাবর্ষ<input name="academic_session" value="{{ old('academic_session') }}" maxlength="40" placeholder="2026" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">বিভাগ / গ্রুপ<input name="academic_group" value="{{ old('academic_group') }}" maxlength="80" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700 sm:col-span-2">বর্তমান ঠিকানা<input name="present_address" value="{{ old('present_address') }}" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700 sm:col-span-2">স্থায়ী ঠিকানা<input name="permanent_address" value="{{ old('permanent_address') }}" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">কোটা / বিশেষ ক্যাটাগরি<input name="quota_type" value="{{ old('quota_type') }}" maxlength="80" placeholder="প্রযোজ্য হলে" class="mt-1 w-full rounded-xl border-slate-300" /></label>
                <label class="text-sm font-semibold text-slate-700">কোটা সংক্রান্ত তথ্য<input name="quota_notes" value="{{ old('quota_notes') }}" maxlength="1000" class="mt-1 w-full rounded-xl border-slate-300" /></label>
            </div>

            <div class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">শিক্ষার্থীর ছবি (ক্যামেরা / ফাইল)<input type="file" name="student_photo" accept="image/*" capture="user" class="mt-1 block w-full text-sm" /></label>
                <label class="text-sm font-semibold text-slate-700">জন্মনিবন্ধন / পরিচয়পত্র<input type="file" name="identity_document" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm" /></label>
                <label class="text-sm font-semibold text-slate-700 sm:col-span-2">কোটা / অতিরিক্ত ডকুমেন্ট<input type="file" name="quota_document" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm" /></label>
                <p class="text-xs text-slate-500 sm:col-span-2">ডকুমেন্টগুলো private storage-এ রাখা হবে এবং Admin যাচাই করতে পারবেন। ছবি সর্বোচ্চ ৫ MB, অন্য ফাইল সর্বোচ্চ ১০ MB।</p>
            </div>

            <label class="block text-sm font-semibold text-slate-700">কোর্স ও ব্যাচ নির্বাচন *</label>
            <select name="coaching_batch_id" required class="w-full rounded-xl border-slate-300"><option value="">ব্যাচ নির্বাচন করুন</option>@foreach($coachingBatches as $batch)<option value="{{ $batch->id }}" @selected(old('coaching_batch_id')==$batch->id)>{{ $batch->name }} — {{ $batch->course?->title }} / {{ $batch->branch?->name }} · ভর্তি ফি ৳{{ number_format((float)$batch->admission_fee,2) }} · মাসিক ৳{{ number_format((float)$batch->monthly_fee,2) }}</option>@endforeach</select>
            <div class="space-y-2 rounded-xl bg-slate-50 p-4 text-sm"><label class="flex items-start gap-2"><input type="checkbox" name="student_sms_consent" value="1" class="mt-1 rounded border-slate-300"><span>শিক্ষার্থীর মোবাইলে ভর্তি, ফি ও ক্লাস সংক্রান্ত SMS পেতে সম্মতি দিচ্ছি।</span></label><label class="flex items-start gap-2"><input type="checkbox" name="guardian_sms_consent" value="1" class="mt-1 rounded border-slate-300"><span>অভিভাবকের নম্বরে ভর্তি, ফি ও শিক্ষার্থী রিপোর্টের SMS পাঠাতে সম্মতি দিচ্ছি।</span></label></div>
            <label class="block text-sm font-semibold text-slate-700">অতিরিক্ত তথ্য<textarea name="applicant_note" rows="3" maxlength="2000" class="mt-1 w-full rounded-xl border-slate-300">{{ old('applicant_note') }}</textarea></label>
            <button class="w-full rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white hover:bg-emerald-800">ভর্তি আবেদন জমা দিন</button>
        </form>
    </div>
</section>
@endsection