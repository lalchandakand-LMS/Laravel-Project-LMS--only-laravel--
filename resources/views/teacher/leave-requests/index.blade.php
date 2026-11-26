@extends('layouts.portal')
@section('title', 'Leave requests')
@section('heading', 'My leave requests')
@section('content')
<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-extrabold text-slate-900">Request history</h2>
        <div class="mt-4 space-y-3">
            @forelse ($requests as $leave)
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-bold text-slate-900">{{ \App\Models\StaffLeaveRequest::TYPES[$leave->leave_type] ?? ucfirst($leave->leave_type) }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $leave->start_date->format('d M Y') }} — {{ $leave->end_date->format('d M Y') }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $leave->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($leave->status === 'rejected' ? 'bg-rose-100 text-rose-700' : ($leave->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600')) }}">{{ ucfirst($leave->status) }}</span>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $leave->reason }}</p>
                    @if ($leave->reviewer_note)
                        <p class="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-600"><strong>Admin note:</strong> {{ $leave->reviewer_note }}</p>
                    @endif
                    @if ($leave->status === 'pending')
                        <form method="POST" action="{{ route('teacher.leave-requests.cancel', $leave) }}" class="mt-3">
                            @csrf
                            <button class="text-xs font-bold text-rose-700">Cancel request</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">You have not submitted a leave request.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $requests->links() }}</div>
    </section>

    <section class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-extrabold text-slate-900">New leave request</h2>
        <form method="POST" action="{{ route('teacher.leave-requests.store') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Leave type</label>
                <select name="leave_type" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach (\App\Models\StaffLeaveRequest::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('leave_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Start date</label>
                <input type="date" name="start_date" min="{{ today()->toDateString() }}" value="{{ old('start_date') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">End date</label>
                <input type="date" name="end_date" min="{{ today()->toDateString() }}" value="{{ old('end_date') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Reason</label>
                <textarea name="reason" rows="4" maxlength="2000" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
            </div>
            @if ($errors->any())<p class="text-xs text-rose-600">{{ $errors->first() }}</p>@endif
            <button class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Submit for approval</button>
        </form>
    </section>
</div>
@endsection
