@extends('layouts.admin')
@section('title', 'Payment reports')
@section('heading', 'Payment reports & ledger')
@section('content')
<div class="space-y-6">
    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <select name="branch_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All branches</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        <select name="gateway" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All methods</option>
            @foreach (['bkash','nagad','rocket','bank','cash','office','admin_manual'] as $method)
                <option value="{{ $method }}" @selected(request('gateway') === $method)>{{ strtoupper($method) }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All statuses</option>
            @foreach (['pending','verified','rejected'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" aria-label="From date">
        <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" aria-label="To date">
        <div class="flex gap-2">
            <button class="flex-1 rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white">Filter</button>
            <a href="{{ route('admin.payment-reports.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600">Reset</a>
        </div>
        <div class="sm:col-span-2 lg:col-span-6">
            <a href="{{ route('admin.payment-reports.export', request()->query()) }}" class="inline-flex rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Export filtered ledger CSV</a>
        </div>
    </form>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([['Gross verified', '৳'.number_format($totals['gross'], 2)], ['Refunds', '৳'.number_format($totals['refunds'], 2)], ['Net collected', '৳'.number_format($totals['net'], 2)], ['Pending', $totals['pending']], ['Rejected', $totals['rejected']]] as [$label, $value])
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-xl font-extrabold text-slate-900">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4">
            <h2 class="font-bold text-slate-900">Payment transactions</h2>
            <p class="mt-1 text-xs text-slate-500">Includes pending, verified and rejected submissions. Only verified amounts contribute to gross collection.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Order / Student</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Method / Reference</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Verified by</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $payment->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3"><a class="font-semibold text-emerald-700" href="{{ route('admin.orders.show', $payment->order->order_no) }}">{{ $payment->order->order_no }}</a><p class="text-xs text-slate-500">{{ $payment->order->user?->name }}</p></td>
                            <td class="px-4 py-3">{{ $payment->order->branch?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><span class="font-semibold uppercase">{{ $payment->gateway }}</span><p class="text-xs text-slate-500">{{ $payment->transaction_id }}</p></td>
                            <td class="px-4 py-3 font-bold">৳{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-4 py-3 capitalize">{{ $payment->status }}</td>
                            <td class="px-4 py-3 text-xs">{{ $payment->verifier?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-slate-500">No payment records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $payments->links() }}</div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4"><h2 class="font-bold text-slate-900">Refund & adjustment records</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Reference / Reason</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Recorded by</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $adjustment->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3"><a class="font-semibold text-emerald-700" href="{{ route('admin.orders.show', $adjustment->order->order_no) }}">{{ $adjustment->order->order_no }}</a></td>
                            <td class="px-4 py-3">{{ $adjustment->order->branch?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><p class="font-semibold">{{ $adjustment->reference ?: '—' }}</p><p class="text-xs text-slate-500">{{ $adjustment->reason }}</p></td>
                            <td class="px-4 py-3 font-bold text-rose-700">−৳{{ number_format($adjustment->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $adjustment->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-slate-500">No refund adjustments match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $adjustments->links() }}</div>
    </section>
</div>
@endsection
