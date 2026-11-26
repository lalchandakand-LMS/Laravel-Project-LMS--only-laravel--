@extends('layouts.admin')
@section('title', 'Enrollments')
@section('heading', 'Enrollment Management')
@section('content')
<div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([['All learning enrollments', $stats['total']], ['Active access', $stats['active']], ['Online batch', $stats['online']], ['Physical coaching', $stats['coaching']]] as [$label, $value])
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ number_format($value) }}</p></div>
    @endforeach
</div>
<div class="mb-5 flex flex-wrap gap-2">
    <a href="{{ route('admin.online-batch-applications.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">Online applications <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ $onlinePending }} pending</span></a>
    <a href="{{ route('admin.coaching-admissions.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">Coaching admissions <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ $coachingPending }} pending</span></a>
    <a href="{{ route('admin.coaching-fees.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">Coaching fee ledger</a>
</div>
@if (! in_array(request('type'), ['online_batch', 'coaching'], true))
<div class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <h2 class="font-bold text-slate-900">Manual enrollment</h2><p class="mb-3 text-xs text-slate-500">Grant or restore course access without creating a payment order.</p>
    <form method="POST" action="{{ route('admin.enrollments.store') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @csrf
        <label class="text-xs font-semibold text-slate-600">Student<select name="user_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Select student</option>@foreach ($students as $student)<option value="{{ $student->id }}" @selected(old('user_id') == $student->id)>{{ $student->name }} — {{ $student->email }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Course<select name="course_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Select course</option>@foreach ($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->title }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Batch (optional)<select name="batch_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">No batch</option>@foreach ($batches as $batch)<option value="{{ $batch->id }}" @selected(old('batch_id') == $batch->id)>{{ $batch->name }} — {{ $batch->course?->title }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Amount paid (৳)<input type="number" name="price_paid" min="0" step="0.01" value="{{ old('price_paid', 0) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-xs font-semibold text-slate-600">Status<select name="status" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach ($statuses as $status)<option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Enrollment date<input type="date" name="enrolled_at" value="{{ old('enrolled_at', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-xs font-semibold text-slate-600">Access expiry<input type="date" name="expires_at" value="{{ old('expires_at') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-xs font-semibold text-slate-600 sm:col-span-2 xl:col-span-4">Admin note / reason<input type="text" name="admin_note" maxlength="5000" value="{{ old('admin_note') }}" placeholder="Scholarship, complimentary access, offline payment, etc." class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <div class="sm:col-span-2 xl:col-span-4"><button class="rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-orange-700">Create manual enrollment</button></div>
    </form>
</div>
@endif
<div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <form method="GET" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Student, email or course" class="rounded-lg border border-slate-300 px-3 py-2 text-sm lg:col-span-2">
        <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All learning paths</option><option value="course" @selected(($filters['type'] ?? '') === 'course')>Course access</option><option value="online_batch" @selected(($filters['type'] ?? '') === 'online_batch')>Online batch</option><option value="coaching" @selected(($filters['type'] ?? '') === 'coaching')>Physical coaching</option></select>
        <select name="course_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All courses</option>@foreach ($courses as $course)<option value="{{ $course->id }}" @selected(($filters['course_id'] ?? '') == $course->id)>{{ $course->title }}</option>@endforeach</select>
        <select name="branch_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All branches</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>@endforeach</select>
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All statuses</option>@foreach ($filterStatuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <div class="flex gap-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="From date" class="min-w-0 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="To date" class="min-w-0 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm"></div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply filters</button><a href="{{ route('admin.enrollments.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Reset</a><a href="{{ route('admin.enrollments.export', request()->query()) }}" class="ml-auto rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700">Export CSV</a></div>
    </form>
</div>
@if (! in_array(request('type'), ['online_batch', 'coaching'], true))
<form method="POST" action="{{ route('admin.enrollments.bulk-update') }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    @csrf
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-3"><span class="mr-auto text-sm font-bold text-slate-800">Enrollment records</span><select name="status" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Bulk change status…</option>@foreach ($statuses as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select><button class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-bold text-white" onclick="return confirm('Apply status to selected enrollments?')">Apply to selected</button></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3"><input type="checkbox" aria-label="Select all" onclick="document.querySelectorAll('.enrollment-select').forEach(el => el.checked = this.checked)"></th><th class="px-4 py-3">Student</th><th class="px-4 py-3">Course / Batch</th><th class="px-4 py-3">Progress</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Paid</th><th class="px-4 py-3">Enrolled / Expiry</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($enrollments as $enrollment)
            <tr><td class="px-4 py-3"><input class="enrollment-select" type="checkbox" name="enrollment_ids[]" value="{{ $enrollment->id }}" aria-label="Select enrollment {{ $enrollment->id }}"></td>
                <td class="px-4 py-3"><a href="{{ route('admin.users.show', $enrollment->user_id) }}" class="font-semibold text-slate-900 hover:text-orange-700">{{ $enrollment->user?->name ?? 'Deleted student' }}</a><p class="text-xs text-slate-500">{{ $enrollment->user?->email }} · {{ $enrollment->user?->branch?->name ?? 'No branch' }}</p></td>
                <td class="px-4 py-3"><p class="font-semibold">{{ $enrollment->course?->title ?? 'Deleted course' }}</p><p class="text-xs text-slate-500">{{ $enrollment->batch?->name ?? 'No batch' }}</p></td>
                <td class="px-4 py-3"><div class="flex items-center gap-2"><div class="h-2 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500" style="width: {{ $enrollment->progress_percent }}%"></div></div><span class="text-xs font-semibold tabular-nums">{{ $enrollment->progress_percent }}%</span></div></td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $enrollment->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($enrollment->status === 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">{{ ucfirst($enrollment->status) }}</span></td>
                <td class="px-4 py-3 font-semibold tabular-nums">৳{{ number_format((float) $enrollment->price_paid, 2) }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ $enrollment->enrolled_at?->format('d M Y') ?? '—' }}<p>Expiry: {{ $enrollment->expires_at?->format('d M Y') ?? 'No expiry' }}</p></td>
                <td class="px-4 py-3 text-right"><div class="flex justify-end gap-3 whitespace-nowrap"><a href="{{ route('admin.enrollments.show', $enrollment) }}" class="font-semibold text-slate-700 hover:text-orange-700">View</a><a href="{{ route('admin.enrollments.edit', $enrollment) }}" class="font-semibold text-orange-700">Edit</a>@if ($enrollment->status !== 'cancelled')<button form="cancel-enrollment-{{ $enrollment->id }}" class="font-semibold text-rose-600" onclick="return confirm('Cancel this enrollment? Learning and payment history will be preserved.')">Cancel</button>@endif</div><form id="cancel-enrollment-{{ $enrollment->id }}" method="POST" action="{{ route('admin.enrollments.destroy', $enrollment) }}" class="hidden">@csrf @method('DELETE')</form></td>
            </tr>
        @empty<tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">No enrollments match your filters.</td></tr>@endforelse
        </tbody></table></div>
    <div class="border-t border-slate-200 p-4">{{ $enrollments->links() }}</div>

</form>
@endif

@if (! in_array(request('type'), ['course', 'coaching'], true))
<section class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 p-4"><div><h2 class="font-bold text-slate-900">Online batch enrollments</h2><p class="text-xs text-slate-500">Approved online live-class batch access. Separate from course purchase access.</p></div><a href="{{ route('admin.online-batch-applications.index') }}" class="text-sm font-semibold text-orange-700">Manage applications</a></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">Course / Online batch</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Admitted</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
    @forelse ($onlineEnrollments as $record)<tr><td class="px-4 py-3"><a href="{{ route('admin.users.show', $record->student) }}" class="font-semibold text-slate-900">{{ $record->student?->name }}</a><p class="text-xs text-slate-500">{{ $record->student?->email }}</p></td><td class="px-4 py-3"><p class="font-semibold">{{ $record->course?->title }}</p><p class="text-xs text-slate-500">{{ $record->batch?->name }}</p></td><td class="px-4 py-3">{{ $record->batch?->branch?->name ?? $record->student?->branch?->name ?? '—' }}</td><td class="px-4 py-3">{{ $record->admitted_at?->format('d M Y') ?? '—' }}</td><td class="px-4 py-3"><span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700">{{ ucfirst($record->status) }}</span></td><td class="px-4 py-3"><a href="{{ route('admin.online-batch-applications.index') }}" class="font-semibold text-orange-700">Application queue</a></td></tr>
    @empty<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No online batch enrollments match these filters.</td></tr>@endforelse
    </tbody></table></div><div class="border-t border-slate-200 p-4">{{ $onlineEnrollments->links() }}</div>
</section>
@endif

@if (! in_array(request('type'), ['course', 'online_batch'], true))
<section class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 p-4"><div><h2 class="font-bold text-slate-900">Physical coaching enrollments</h2><p class="text-xs text-slate-500">Branch-based coaching access activated after admission requirements and fees.</p></div><a href="{{ route('admin.coaching-admissions.index') }}" class="text-sm font-semibold text-orange-700">Manage admissions</a></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">Course / Coaching batch</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Admitted</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
    @forelse ($coachingEnrollments as $record)<tr><td class="px-4 py-3"><a href="{{ route('admin.users.show', $record->student) }}" class="font-semibold text-slate-900">{{ $record->student?->name }}</a><p class="text-xs text-slate-500">{{ $record->student?->email }}</p></td><td class="px-4 py-3"><p class="font-semibold">{{ $record->course?->title }}</p><p class="text-xs text-slate-500">{{ $record->batch?->name }}</p></td><td class="px-4 py-3">{{ $record->batch?->branch?->name ?? '—' }}</td><td class="px-4 py-3">{{ $record->admitted_at?->format('d M Y') ?? '—' }}</td><td class="px-4 py-3"><span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700">{{ ucfirst($record->status) }}</span></td><td class="px-4 py-3"><div class="flex flex-col gap-1"><a href="{{ route('admin.coaching-admissions.index') }}" class="font-semibold text-orange-700">Admission records</a><a href="{{ route('admin.coaching-fees.index') }}" class="text-xs font-semibold text-slate-600">Fee ledger</a></div></td></tr>
    @empty<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No physical coaching enrollments match these filters.</td></tr>@endforelse
    </tbody></table></div><div class="border-t border-slate-200 p-4">{{ $coachingEnrollments->links() }}</div>
</section>
@endif
@endsection
