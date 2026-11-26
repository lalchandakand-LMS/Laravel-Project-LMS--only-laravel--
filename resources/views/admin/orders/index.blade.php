@extends('layouts.admin')
@section('title', 'Orders')
@section('heading', 'Orders')
@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.orders.export', request()->query()) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Export CSV</a>
        <a href="{{ route('admin.orders.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">+ Create order</a>
    </div>
@endsection
@section('content')
<div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    @foreach ([['All orders', $totals['all']], ['Paid', $totals['paid']], ['Pending / partial', $totals['pending']], ['Cancelled', $totals['cancelled']], ['Net revenue', '৳'.number_format($totals['revenue'], 2)]] as [$label, $value])
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $value }}</p>
        </div>
    @endforeach
</div>

<form method="GET" class="mb-4 rounded-2xl border border-slate-200 bg-white p-4">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <input name="q" value="{{ request('q') }}" placeholder="Order no / customer / phone / email" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All order statuses</option>
            @foreach (['pending', 'partially_paid', 'paid', 'failed', 'cancelled', 'refunded'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
        <select name="branch_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All branches</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        <select name="item_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All item types</option>
            <option value="course" @selected(request('item_type') === 'course')>Courses</option>
            <option value="ebook" @selected(request('item_type') === 'ebook')>E-books</option>
        </select>
        <label class="text-xs text-slate-500">From date<input type="date" name="from" value="{{ request('from') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"></label>
        <label class="text-xs text-slate-500">To date<input type="date" name="to" value="{{ request('to') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"></label>
    </div>
    <div class="mt-3 flex gap-2">
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply filters</button>
        <a href="{{ route('admin.orders.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Reset</a>
    </div>
</form>

<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-5 py-3">Order</th><th class="px-5 py-3">Customer / Branch</th><th class="px-5 py-3">Items</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Received / Due</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Date</th><th class="px-5 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($orders as $order)
                @php($received = (float) $order->verified_paid - (float) $order->refund_total + (float) $order->credit_total)
                <tr>
                    <td class="px-5 py-3 font-mono font-semibold text-slate-800">{{ $order->order_no }}</td>
                    <td class="px-5 py-3"><p class="font-semibold text-slate-700">{{ $order->name ?: optional($order->user)->name }}</p><p class="text-xs text-slate-500">{{ $order->phone }} · {{ $order->branch?->name ?? 'No branch' }}</p></td>
                    <td class="px-5 py-3">{{ $order->items_count }}</td>
                    <td class="px-5 py-3 font-bold">৳{{ number_format($order->total, 2) }}</td>
                    <td class="px-5 py-3"><p class="font-semibold text-emerald-700">৳{{ number_format(max(0, $received), 2) }}</p><p class="text-xs text-rose-600">Due ৳{{ number_format(max(0, $order->total - $received), 2) }}</p></td>
                    <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $order->statusBadge() }}">{{ str_replace('_', ' ', $order->status) }}</span></td>
                    <td class="px-5 py-3 text-xs text-slate-500">{{ $order->created_at->format('d M Y H:i') }}</td>
                    <td class="px-5 py-3 text-right"><a href="{{ route('admin.orders.show', $order->order_no) }}" class="font-semibold text-emerald-700">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-10 text-center text-slate-500">No orders match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
