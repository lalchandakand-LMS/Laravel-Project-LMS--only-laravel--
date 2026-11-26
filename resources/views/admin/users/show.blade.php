@extends('layouts.admin')
@section('title', $user->name)
@section('heading', $user->name)
@section('actions')
    <a href="{{ route('admin.users.edit', $user) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Edit user</a>
@endsection
@section('content')
<div class="grid gap-6 lg:grid-cols-[1fr_2fr]">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <img src="{{ $user->avatarUrl() }}" class="mx-auto h-24 w-24 rounded-full object-cover" alt="">
        <h3 class="mt-3 text-lg font-bold text-slate-900">{{ $user->name }}</h3>
        <p class="text-sm text-slate-600">{{ $user->email }}</p>
        <p class="mt-1 text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $user->roleLabel() }}</p>
        <dl class="mt-5 space-y-2 text-left text-sm">
            <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><dt class="text-slate-500">Phone</dt><dd class="font-semibold">{{ $user->phone ?? '—' }}</dd></div>
            <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><dt class="text-slate-500">Branch</dt><dd class="font-semibold">{{ $user->branch?->name ?? '—' }}</dd></div>
            <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><dt class="text-slate-500">Joined</dt><dd class="font-semibold">{{ $user->created_at->format('d M Y') }}</dd></div>
            <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><dt class="text-slate-500">Login status</dt><dd class="font-semibold">{{ $user->is_active ? 'Active' : 'Disabled' }}</dd></div>
        </dl>
    </div>

    <div class="space-y-6">
        @if ($user->studentProfile)
            @php($profile = $user->studentProfile)
            <section class="rounded-2xl border border-sky-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 px-5 py-4 font-bold">Student academic profile</h3>
                <dl class="grid gap-3 p-5 text-sm sm:grid-cols-2">
                    @foreach (['student_code'=>'Student ID','date_of_birth'=>'Date of birth','gender'=>'Gender','school_name'=>'School / College','academic_class'=>'Class','academic_roll'=>'Roll','academic_session'=>'Session','academic_group'=>'Group','present_address'=>'Present address','permanent_address'=>'Permanent address','emergency_contact_name'=>'Emergency contact','emergency_contact_phone'=>'Emergency phone','status'=>'Student status'] as $field=>$label)
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ $profile->{$field} instanceof \Carbon\CarbonInterface ? $profile->{$field}->format('d M Y') : ($profile->{$field} ?: '—') }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($user->staffProfile)
            @php($profile = $user->staffProfile)
            <section class="rounded-2xl border border-amber-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 px-5 py-4 font-bold">Staff employment profile</h3>
                <dl class="grid gap-3 p-5 text-sm sm:grid-cols-2">
                    @foreach (['employee_code'=>'Employee ID','department'=>'Department','designation'=>'Designation','employment_type'=>'Employment type','joining_date'=>'Joining date','confirmation_date'=>'Confirmation date','experience_years'=>'Experience (years)','highest_qualification'=>'Qualification','institution'=>'Institution','employment_status'=>'Employment status'] as $field=>$label)
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ $profile->{$field} instanceof \Carbon\CarbonInterface ? $profile->{$field}->format('d M Y') : ($profile->{$field} ?: '—') }}</dd></div>
                    @endforeach
                    <div class="rounded-lg bg-slate-50 p-3 sm:col-span-2"><dt class="text-xs text-slate-500">Professional bio</dt><dd class="mt-1 whitespace-pre-line font-semibold">{{ $profile->professional_bio ?: '—' }}</dd></div>
                </dl>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Enrollments ({{ $user->enrollments->count() }})</h3>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($user->enrollments as $enrollment)
                    <li class="flex justify-between px-5 py-3"><span>{{ optional($enrollment->course)->title }}</span><span class="text-slate-500">৳{{ number_format($enrollment->price_paid) }} · {{ $enrollment->created_at->format('d M Y') }}</span></li>
                @empty
                    <li class="px-5 py-3 text-slate-500">No enrollments.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Exam attempts</h3>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($user->examAttempts as $attempt)
                    <li class="flex justify-between px-5 py-3"><span>{{ optional($attempt->exam)->title }}</span><span class="font-semibold {{ $attempt->isPassed() ? 'text-emerald-700' : 'text-rose-600' }}">{{ $attempt->score }} marks</span></li>
                @empty
                    <li class="px-5 py-3 text-slate-500">No attempts.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Orders</h3>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($user->orders as $order)
                    <li class="flex justify-between px-5 py-3"><a href="{{ route('admin.orders.show', $order->order_no) }}" class="font-semibold text-emerald-700">#{{ $order->order_no }}</a><span>৳{{ number_format($order->total) }} · <span class="capitalize">{{ $order->status }}</span></span></li>
                @empty
                    <li class="px-5 py-3 text-slate-500">No orders.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
