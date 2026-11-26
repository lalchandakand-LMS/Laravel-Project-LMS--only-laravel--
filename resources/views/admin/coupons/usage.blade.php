@extends('layouts.admin')
@section('title', 'Coupon usage report')
@section('heading', 'Coupon usage report')
@section('actions')
    <a href="{{ route('admin.coupons.usage.export', request()->query()) }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white">Export CSV</a>
@endsection
@section('content')
<form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
    <select name="coupon_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All coupons</option>@foreach($coupons as $coupon)<option value="{{ $coupon->id }}" @selected((string)request('coupon_id') === (string)$coupon->id)>{{ $coupon->code }}</option>@endforeach</select>
    <select name="branch_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)request('branch_id') === (string)$branch->id)>{{ $branch->name }}</option>@endforeach</select>
    <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All statuses</option>@foreach(['pending','partially_paid','paid','failed','cancelled','refunded'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_',' ',ucfirst($status)) }}</option>@endforeach</select>
    <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <div class="flex gap-2 lg:col-span-5"><button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('admin.coupons.usage') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Reset</a></div>
</form>
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
<table class="w-full text-sm">
    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Order / Date</th><th class="px-4 py-3">Coupon</th><th class="px-4 py-3">Student</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Subtotal</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Status</th></tr></thead>
    <tbody class="divide-y divide-slate-100">
    @forelse($orders as $order)
        <tr><td class="px-4 py-3"><strong>{{ $order->order_no }}</strong><div class="text-xs text-slate-500">{{ $order->created_at?->format('d M Y H:i') }}</div></td><td class="px-4 py-3 font-mono">{{ $order->coupon?->code ?? '—' }}</td><td class="px-4 py-3">{{ $order->user?->name }}<div class="text-xs text-slate-500">{{ $order->user?->email }}</div></td><td class="px-4 py-3">{{ $order->branch?->name ?? '—' }}</td><td class="px-4 py-3">৳{{ number_format($order->subtotal,2) }}</td><td class="px-4 py-3 text-emerald-700">৳{{ number_format($order->discount,2) }}</td><td class="px-4 py-3">৳{{ number_format($order->total,2) }}</td><td class="px-4 py-3">{{ str_replace('_',' ',ucfirst($order->status)) }}</td></tr>
    @empty
        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">No coupon orders match these filters.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
