@extends('layouts.app')
@section('title', 'Payment receipt '.$order->order_no)
@section('content')
<div class="mx-auto max-w-3xl px-4 py-10">
    <div class="mb-4 flex justify-end print:hidden">
        <button onclick="window.print()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Print receipt</button>
    </div>
    <article class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm print:border-0 print:shadow-none">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</h1>
                <p class="mt-1 text-sm text-slate-500">Official payment receipt</p>
                @if ($order->branch)<p class="mt-1 text-xs text-slate-500">{{ $order->branch->name }} · {{ $order->branch->address }}</p>@endif
            </div>
            <div class="text-right">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Receipt</p>
                <p class="font-extrabold text-slate-900">RCPT-{{ $order->order_no }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ now()->format('d M Y, h:i A') }}</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
            <div><p class="text-xs text-slate-500">Student</p><p class="font-bold">{{ $order->user?->name ?? $order->name }}</p><p class="text-slate-600">{{ $order->email }}</p><p class="text-slate-600">{{ $order->phone }}</p></div>
            <div><p class="text-xs text-slate-500">Order number</p><p class="font-bold">{{ $order->order_no }}</p><p class="mt-1 text-xs text-slate-500">Order date: {{ $order->created_at->format('d M Y') }}</p><p class="mt-1 text-xs font-bold uppercase text-slate-600">Status: {{ str_replace('_', ' ', $order->status) }}</p></div>
        </div>

        @if($order->items->contains('item_type', 'product'))<div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm"><b>Delivery address</b><p class="mt-1 whitespace-pre-line">{{ $order->address }}</p>
                    @if($order->shipping_status)
                        <p class="mt-3 text-sm"><b>Delivery status:</b> {{ ucfirst($order->shipping_status) }}</p>
                        @if($order->shipping_carrier || $order->tracking_number)
                            <p class="mt-1 text-sm">Carrier: {{ $order->shipping_carrier ?: '—' }} · Tracking: {{ $order->tracking_number ?: '—' }}</p>
                        @endif
                        @if($order->shipped_at)<p class="mt-1 text-xs text-slate-500">Shipped {{ $order->shipped_at->format('d M Y, h:i A') }}</p>@endif
                        @if($order->delivered_at)<p class="mt-1 text-xs text-slate-500">Delivered {{ $order->delivered_at->format('d M Y, h:i A') }}</p>@endif
                    @endif</div>@endif

        <table class="mt-6 w-full text-sm">
            <thead><tr class="border-y border-slate-200 text-left text-xs uppercase text-slate-500"><th class="py-2">Description</th><th class="py-2 text-right">Amount</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($order->items as $item)
                    <tr><td class="py-3">{{ $item->title }} @if($item->qty > 1)<span class="text-xs text-slate-500">× {{ $item->qty }}</span>@endif</td><td class="py-3 text-right">৳{{ number_format($item->price * $item->qty, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <dl class="ml-auto mt-5 max-w-xs space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>৳{{ number_format($order->subtotal, 2) }}</dd></div>
            @if($order->discount > 0)<div class="flex justify-between text-emerald-700"><dt>Discount</dt><dd>−৳{{ number_format($order->discount, 2) }}</dd></div>@endif
            @if($order->items->contains('item_type', 'product'))<div class="flex justify-between"><dt class="text-slate-500">Delivery fee</dt><dd>{{ $order->shipping_fee > 0 ? '৳'.number_format($order->shipping_fee, 2) : 'Free' }}</dd></div>@endif
            <div class="flex justify-between"><dt class="text-slate-500">Order total</dt><dd class="font-semibold">৳{{ number_format($order->total, 2) }}</dd></div>
            <div class="flex justify-between text-emerald-700"><dt>Net received after refunds</dt><dd class="font-bold">৳{{ number_format($order->amountPaid(), 2) }}</dd></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 font-extrabold"><dt>Balance due</dt><dd>৳{{ number_format($order->dueAmount(), 2) }}</dd></div>
        </dl>

        <h2 class="mt-8 border-b border-slate-200 pb-2 font-bold">Verified payment records</h2>
        <div class="mt-2 space-y-2 text-sm">
            @forelse ($order->payments->where('status', 'verified') as $payment)
                <div class="flex justify-between gap-3 border-b border-slate-100 py-2">
                    <div><p class="font-semibold">{{ strtoupper($payment->gateway) }} · {{ $payment->transaction_id }}</p><p class="text-xs text-slate-500">{{ optional($payment->verified_at)->format('d M Y, h:i A') }} @if($payment->verifier) · {{ $payment->verifier->name }} @endif</p></div>
                    <p class="font-bold">৳{{ number_format($payment->amount, 2) }}</p>
                </div>
            @empty
                <p class="py-2 text-slate-500">No verified payment recorded yet.</p>
            @endforelse
        </div>

        @if ($order->adjustments->count())
            <h2 class="mt-6 border-b border-slate-200 pb-2 font-bold">Refund adjustments</h2>
            <div class="mt-2 space-y-2 text-sm">
                @foreach ($order->adjustments as $adjustment)
                    <div class="flex justify-between gap-3 border-b border-slate-100 py-2">
                        <div><p class="font-semibold text-rose-700">{{ ucfirst($adjustment->type) }} · {{ $adjustment->reference ?: 'No reference' }}</p><p class="text-xs text-slate-500">{{ $adjustment->reason }}</p></div>
                        <p class="font-bold text-rose-700">−৳{{ number_format($adjustment->amount, 2) }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="mt-8 border-t border-slate-200 pt-4 text-center text-xs text-slate-500">This is a computer-generated receipt. Please retain it for your records.</p>
    </article>
</div>
@endsection
