@extends('layouts.admin')
@section('title', 'Library loans')
@section('heading', 'Library issue & return desk')
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">Approve requests, issue copies, record returns and fines.</p>
    <form method="GET" class="flex gap-2">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All statuses</option>@foreach (['requested','issued','returned','rejected','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))> Overdue only</label>
        <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Filter</button>
    </form>
</div>
<div class="space-y-4">
    @forelse ($loans as $loan)
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h3 class="font-extrabold text-slate-900">{{ $loan->book?->title }}</h3><p class="text-xs text-slate-500">{{ $loan->student?->name }} · {{ $loan->student?->email }}</p><p class="mt-1 text-xs text-slate-500">{{ $loan->book?->branch?->name ?? 'All branches' }} · Requested {{ $loan->requested_at?->format('d M Y, h:i A') }}</p></div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $loan->status === 'issued' ? ($loan->isOverdue() ? 'bg-rose-100 text-rose-700' : 'bg-sky-100 text-sky-700') : ($loan->status === 'returned' ? 'bg-emerald-100 text-emerald-700' : ($loan->status === 'requested' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600')) }}">{{ $loan->isOverdue() ? 'Overdue' : ucfirst($loan->status) }}</span>
            </div>
            @if ($loan->status === 'requested')
                <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 md:grid-cols-[1fr_auto]">
                    <form method="POST" action="{{ route('admin.library.loans.issue', $loan) }}" class="flex flex-wrap gap-2">@csrf
                        <input type="datetime-local" name="due_at" required value="{{ old('due_at', now()->addDays(14)->format('Y-m-d\TH:i')) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input name="notes" placeholder="Issue note (optional)" class="min-w-40 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve & issue</button>
                    </form>
                    <form method="POST" action="{{ route('admin.library.loans.reject', $loan) }}" class="flex gap-2">@csrf<input name="notes" placeholder="Rejection note" class="min-w-36 rounded-lg border border-slate-300 px-3 py-2 text-sm"><button class="rounded-lg border border-rose-300 px-3 py-2 text-xs font-bold text-rose-700">Reject</button></form>
                </div>
            @elseif ($loan->status === 'issued')
                <p class="mt-3 text-xs text-slate-500">Issued {{ $loan->issued_at?->format('d M Y') }} · Due {{ $loan->due_at?->format('d M Y, h:i A') }} · Issued by {{ $loan->issuer?->name }}</p>
                <form method="POST" action="{{ route('admin.library.loans.return', $loan) }}" class="mt-3 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-3">@csrf
                    <input type="number" name="fine_amount" min="0" step="0.01" placeholder="Fine amount" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="return_condition" placeholder="Return condition / note" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-lg bg-indigo-700 px-4 py-2 text-xs font-bold text-white">Record return</button>
                </form>
            @elseif ($loan->status === 'returned')
                <p class="mt-3 text-xs text-slate-500">Returned {{ $loan->returned_at?->format('d M Y, h:i A') }} · Fine ৳{{ number_format($loan->fine_amount, 2) }} · {{ $loan->return_condition }}</p>
            @endif
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No library loans found.</div>
    @endforelse
</div>
<div class="mt-4">{{ $loans->links() }}</div>
@endsection
