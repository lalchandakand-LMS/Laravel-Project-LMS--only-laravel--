@extends('layouts.admin')
@section('title', 'Staff leave requests')
@section('heading', 'Staff leave requests')
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">Review staff leave requests and prevent overlapping approved leave for the same person.</p>
    <form method="GET" class="flex items-center gap-2">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All statuses</option>
            @foreach (['pending','approved','rejected','cancelled'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Filter</button>
    </form>
</div>
<div class="space-y-4">
    @forelse ($requests as $leave)
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-extrabold text-slate-900">{{ $leave->user?->name }}</h3>
                    <p class="text-xs text-slate-500">{{ $leave->user?->email }} · {{ $leave->user?->roleLabel() }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-700">{{ \App\Models\StaffLeaveRequest::TYPES[$leave->leave_type] ?? ucfirst($leave->leave_type) }} · {{ $leave->start_date->format('d M Y') }} — {{ $leave->end_date->format('d M Y') }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $leave->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($leave->status === 'rejected' ? 'bg-rose-100 text-rose-700' : ($leave->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600')) }}">{{ ucfirst($leave->status) }}</span>
            </div>
            <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $leave->reason }}</p>
            @if ($leave->reviewer_note)<p class="mt-2 text-sm text-slate-500">Previous note: {{ $leave->reviewer_note }}</p>@endif
            @if ($leave->status === 'pending')
                <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 md:grid-cols-[1fr_auto_auto]">
                    <form method="POST" action="{{ route('admin.staff-leave-requests.approve', $leave) }}" class="flex flex-wrap gap-2">
                        @csrf
                        <input name="reviewer_note" placeholder="Optional admin note" class="min-w-48 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.staff-leave-requests.reject', $leave) }}" class="flex flex-wrap gap-2">
                        @csrf
                        <input name="reviewer_note" placeholder="Reason (optional)" class="min-w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg border border-rose-300 px-4 py-2 text-xs font-bold text-rose-700">Reject</button>
                    </form>
                </div>
            @else
                <p class="mt-3 text-xs text-slate-500">Reviewed {{ $leave->reviewed_at?->format('d M Y, h:i A') ?? '—' }} by {{ $leave->reviewer?->name ?? '—' }}</p>
            @endif
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No staff leave requests found.</div>
    @endforelse
</div>
<div class="mt-4">{{ $requests->links() }}</div>
@endsection
