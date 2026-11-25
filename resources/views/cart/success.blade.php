@extends('layouts.app')
@section('title', 'Order placed')
@section('content')
<div class="mx-auto max-w-3xl px-4 py-12">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="flex items-center gap-3">
            <span class="grid h-12 w-12 place-items-center rounded-full bg-emerald-100 text-2xl">✓</span>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Order #{{ $order->order_no }}</h1>
                <p class="text-sm text-slate-600">Placed {{ $order->created_at->format('d M Y, h:i A') }}</p>
            </div>
            <span class="ml-auto rounded-full px-3 py-1 text-xs font-bold {{ $order->statusBadge() }}">{{ strtoupper($order->status) }}</span>
        </div>

        <ul class="mt-6 divide-y divide-slate-100 border-y border-slate-100">
            @foreach ($order->items as $item)
                <li class="flex justify-between py-3 text-sm">
                    <span><span class="mr-2 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase text-slate-500">{{ $item->item_type }}</span>{{ $item->title }}</span>
                    <span class="font-semibold">৳{{ number_format($item->price, 2) }}</span>
                </li>
            @endforeach
        </ul>
        <div class="mt-3 flex justify-between text-base font-extrabold">
            <span>Total</span><span>৳{{ number_format($order->total, 2) }}</span>
        </div>
        <p class="mt-2 text-xs text-slate-500">Branch: {{ $branch?->name ?? 'Not assigned' }}</p>

        @if ($order->status === 'paid')
            <div class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">Payment confirmed — your courses are now unlocked.</div>
            <a href="{{ route('student.courses') }}" class="mt-4 inline-block rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white">Go to my courses</a>
        @else
            @if (count($enabledMethods))
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    <p class="font-bold">Complete your payment</p>
                    <p class="mt-1">Select one of the payment methods enabled by your branch ({{ $branch->name }}).</p>
                    @if (in_array('bkash', $enabledMethods, true) && $paymentNumbers['bkash'])
                        <p class="mt-2">bKash number: <b>{{ $paymentNumbers['bkash'] }}</b></p>
                    @endif
                    @if (in_array('nagad', $enabledMethods, true) && $paymentNumbers['nagad'])
                        <p class="mt-1">Nagad number: <b>{{ $paymentNumbers['nagad'] }}</b></p>
                    @endif
                    <p class="mt-2">Submit the transaction details. Your payment remains pending until staff verifies it.</p>
                </div>

                <form method="POST" action="{{ route('checkout.pay', $order->order_no) }}" class="mt-4 grid gap-3 rounded-xl border border-slate-200 p-5">
                    @csrf
                    <label class="text-sm font-semibold text-slate-700">Payment method</label>
                    <select name="gateway" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($enabledMethods as $method)
                            <option value="{{ $method }}">{{ \App\Models\Branch::PAYMENT_METHODS[$method] ?? strtoupper($method) }}</option>
                        @endforeach
                    </select>
                    <label class="text-sm font-semibold text-slate-700">Sender number / Account name</label>
                    <input name="sender_number" placeholder="Sender number or account name" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <label class="text-sm font-semibold text-slate-700">Transaction ID / Deposit reference</label>
                    <input name="transaction_id" placeholder="Transaction ID or bank reference" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Submit for verification</button>
                </form>
            @else
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    No online payment method is currently enabled for this branch. Please contact the branch office for cash or office payment.
                </div>
            @endif

            @if ($order->payments->count())
                <div class="mt-4 space-y-2">
                    @foreach ($order->payments as $payment)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-2 text-sm">
                            <span>{{ strtoupper($payment->gateway) }} · {{ $payment->transaction_id }}</span>
                            <span class="font-semibold capitalize {{ $payment->status === 'verified' ? 'text-emerald-700' : ($payment->status === 'rejected' ? 'text-rose-600' : 'text-amber-600') }}">{{ $payment->status }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
