@extends('layouts.admin')
@section('title', $user->name)
@section('heading', $user->name)
@section('actions')
    @if(in_array($user->role, ['student','instructor','branch_manager'], true))<a href="{{ route('admin.users.id-card', $user) }}" class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-bold text-emerald-700">Print ID card</a>@endif
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
            <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><dt class="text-slate-500">Phone</dt><dd class="text-right font-semibold">{{ $user->phone ?? '—' }}<span class="block text-xs {{ $user->phone_verified_at ? 'text-emerald-700' : 'text-amber-700' }}">{{ $user->phone_verified_at ? 'Verified' : 'Not verified' }}</span></dd></div>
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
                    @foreach (['student_code'=>'Student ID','date_of_birth'=>'Date of birth','gender'=>'Gender','school_name'=>'School / College','academic_class'=>'Class','academic_roll'=>'Roll','academic_session'=>'Session','academic_group'=>'Group','present_address'=>'Present address','permanent_address'=>'Permanent address','emergency_contact_name'=>'Emergency contact','emergency_contact_phone'=>'Emergency phone','guardian_name'=>'Guardian name','guardian_phone'=>'Guardian phone','guardian_email'=>'Guardian email','quota_type'=>'Quota / special category','quota_notes'=>'Quota notes','status'=>'Student status'] as $field=>$label)
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

        @if ($user->studentProfile)
            <section class="rounded-2xl border border-sky-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 px-5 py-4 font-bold">Student attendance history</h3>
                <div class="divide-y divide-slate-100">
                    @forelse ($user->studentProfile->attendanceRecords->take(8) as $attendance)
                        <div class="flex justify-between px-5 py-3 text-sm"><span>{{ $attendance->session?->topic ?? 'Class attendance' }} · {{ $attendance->session?->starts_at?->format('Y-m-d') ?? $attendance->created_at->format('Y-m-d') }}</span><strong class="capitalize">{{ $attendance->status }}</strong></div>
                    @empty
                        <p class="px-5 py-4 text-sm text-slate-500">No attendance records.</p>
                    @endforelse
                </div>
            </section>
        @endif

        @if ($user->staffProfile)
            <section class="rounded-2xl border border-amber-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 px-5 py-4 font-bold">Staff attendance & payroll history</h3>
                <div class="grid gap-4 p-5 lg:grid-cols-2">
                    <div><h4 class="mb-2 text-sm font-bold">Recent attendance</h4>
                        @forelse ($user->staffProfile->attendances->take(8) as $attendance)
                            <div class="flex justify-between border-b py-2 text-xs"><span>{{ $attendance->attendance_date->format('d M Y') }}</span><strong class="capitalize">{{ str_replace('_',' ',$attendance->status) }}</strong></div>
                        @empty
                            <p class="text-sm text-slate-500">No staff attendance records.</p>
                        @endforelse
                    </div>
                    <div><h4 class="mb-2 text-sm font-bold">Payroll history</h4>
                        @forelse ($user->staffProfile->payrolls->take(8) as $payroll)
                            <div class="flex justify-between border-b py-2 text-xs"><span>{{ $payroll->payroll_month }} · {{ ucfirst($payroll->status) }}</span><strong>৳{{ number_format((float)$payroll->net_salary, 2) }}</strong></div>
                        @empty
                            <p class="text-sm text-slate-500">No payroll records.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        @endif

        @if (in_array($user->role, ['student', 'instructor', 'branch_manager'], true))
            @php($profile = $user->studentProfile ?? $user->staffProfile)
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 px-5 py-4 font-bold">Identity & qualification documents</h3>
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.users.documents.store', $user) }}" class="grid gap-3 p-5 sm:grid-cols-3">
                    @csrf
                    <div><label class="mb-1 block text-xs font-semibold">Document type</label><select name="document_type" required class="w-full rounded-lg border px-3 py-2 text-sm">@foreach (['birth_certificate'=>'Birth certificate','nid'=>'NID','passport'=>'Passport','photo'=>'Photo','certificate'=>'Academic certificate','cv'=>'CV','appointment_letter'=>'Appointment letter','other'=>'Other'] as $v=>$label)<option value="{{ $v }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="sm:col-span-2"><label class="mb-1 block text-xs font-semibold">File (PDF/JPG/PNG/WebP, max 10 MB)</label><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full text-sm"></div>
                    <div class="sm:col-span-3"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Upload for verification</button></div>
                </form>
                <div class="divide-y divide-slate-100">
                    @forelse (($profile?->documents ?? collect()) as $document)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div><p class="font-semibold">{{ $document->original_name }}</p><p class="text-xs text-slate-500">{{ str_replace('_', ' ', ucfirst($document->document_type)) }} · {{ number_format($document->size_bytes / 1024, 1) }} KB · {{ $document->created_at->format('d M Y') }}</p><p class="text-xs {{ $document->status === 'approved' ? 'text-emerald-700' : ($document->status === 'rejected' ? 'text-rose-700' : 'text-amber-700') }}">Status: {{ ucfirst($document->status) }} {{ $document->review_note ? '— '.$document->review_note : '' }}</p></div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.users.documents.download', [$user, $document]) }}" class="font-semibold text-emerald-700">Download</a>
                                @if ($document->status === 'pending')
                                    <form method="POST" action="{{ route('admin.users.documents.review', [$user, $document]) }}" class="flex items-center gap-1">@csrf<input type="hidden" name="status" value="approved"><button class="rounded bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">Approve</button></form>
                                    <form method="POST" action="{{ route('admin.users.documents.review', [$user, $document]) }}" class="flex items-center gap-1">@csrf<input type="hidden" name="status" value="rejected"><input name="review_note" placeholder="Reason" class="w-24 rounded border px-2 py-1 text-xs"><button class="rounded bg-rose-50 px-2 py-1 text-xs font-bold text-rose-700">Reject</button></form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-slate-500">No documents uploaded yet.</p>
                    @endforelse
                </div>
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
