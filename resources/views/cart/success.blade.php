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
                    <span><span class="mr-2 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase text-slate-500">{{ $item->item_type }}</span>{{ $item->title }} @if($item->qty > 1)<span class="text-xs text-slate-500">× {{ $item->qty }}</span>@endif</span>
                    <span class="font-semibold">৳{{ number_format($item->price, 2) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between font-extrabold"><dt>Total</dt><dd>৳{{ number_format($order->total, 2) }}</dd></div>
            <div class="flex justify-between text-emerald-700"><dt>Verified amount</dt><dd>৳{{ number_format($order->amountPaid(), 2) }}</dd></div>
            <div class="flex justify-between font-bold text-rose-700"><dt>Remaining due</dt><dd>৳{{ number_format($order->dueAmount(), 2) }}</dd></div>
        </dl>
        <p class="mt-2 text-xs text-slate-500">Branch: {{ $branch?->name ?? 'Not assigned' }}</p>
        @if ($order->amountPaid() > 0)
            <a href="{{ route('orders.receipt', $order->order_no) }}" class="mt-3 inline-block rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-emerald-700">View / print payment receipt</a>
        @endif

        @if ($order->status === 'paid')
            <div class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">Payment fully confirmed. Your digital access is active and physical products are ready for order processing.</div>
            <a href="{{ route('student.courses') }}" class="mt-4 inline-block rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white">Go to my courses</a>
            @if($order->items->contains('item_type', 'product'))
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <b>Delivery address</b>
                    <p class="mt-1 whitespace-pre-line">{{ $order->address }}</p>
                    <p class="mt-2 text-xs text-slate-500">The branch will process and dispatch your physical items.</p>
                </div>
            @endif
        @else
            @if (count($enabledMethods) && $order->dueAmount() > 0)
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    <p class="font-bold">Complete your payment</p>
                    <p class="mt-1">Choose a method enabled for {{ $branch->name }}. Partial payments are accepted; access starts after the full amount is verified.</p>
                    @if (in_array('bkash', $enabledMethods, true) && $branch->bkash_number)
                        <p class="mt-2">bKash number: <b>{{ $branch->bkash_number }}</b></p>
                    @endif
                    @if (in_array('nagad', $enabledMethods, true) && $branch->nagad_number)
                        <p class="mt-1">Nagad number: <b>{{ $branch->nagad_number }}</b></p>
                    @endif
                    @if (in_array('rocket', $enabledMethods, true) && $branch->rocket_number)
                        <p class="mt-1">Rocket number: <b>{{ $branch->rocket_number }}</b></p>
                    @endif
                    @if (in_array('bank', $enabledMethods, true))
                        <div class="mt-2 rounded-lg bg-white/70 p-3">
                            <p class="font-bold">Bank transfer details</p>
                            <p>Bank: {{ $branch->bank_name ?: 'Contact branch' }}</p>
                            <p>Branch: {{ $branch->bank_branch ?: '—' }}</p>
                            <p>Account name: {{ $branch->bank_account_name ?: '—' }}</p>
                            <p>Account number: {{ $branch->bank_account_number ?: '—' }}</p>
                        </div>
                    @endif
                    @if ($branch->payment_instructions)
                        <p class="mt-2 whitespace-pre-line">{{ $branch->payment_instructions }}</p>
                    @endif
                </div>

                @if (! $order->payments->where('status', 'pending')->count())
                    <form method="POST" enctype="multipart/form-data" action="{{ route('checkout.pay', $order->order_no) }}" class="mt-4 grid gap-3 rounded-xl border border-slate-200 p-5">
                        @csrf
                        <label class="text-sm font-semibold text-slate-700">Payment method</label>
                        <select name="gateway" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($enabledMethods as $method)
                                <option value="{{ $method }}">{{ \App\Models\Branch::PAYMENT_METHODS[$method] ?? strtoupper($method) }}</option>
                            @endforeach
                        </select>
                        <label class="text-sm font-semibold text-slate-700">Amount to pay (up to ৳{{ number_format($order->dueAmount(), 2) }})</label>
                        <input type="number" name="amount" min="1" max="{{ $order->dueAmount() }}" step="0.01" value="{{ old('amount', $order->dueAmount()) }}" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <label class="text-sm font-semibold text-slate-700">Sender number / Account name</label>
                        <input name="sender_number" value="{{ old('sender_number') }}" placeholder="Sender number or account name" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <label class="text-sm font-semibold text-slate-700">Transaction ID / Deposit reference</label>
                        <input name="transaction_id" value="{{ old('transaction_id') }}" placeholder="Transaction ID or bank reference" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <label class="text-sm font-semibold text-slate-700">Deposit slip / receipt (required for bank transfer)</label>
                        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('proof')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                        <button class="rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Submit for verification</button>
                    </form>
                @else
                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">Your payment is awaiting verification. You can submit another payment after this one has been approved or rejected.</div>
                @endif
            @elseif ($order->dueAmount() > 0)
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    No online payment method is currently enabled for this branch. Please contact the branch office for cash or office payment.
                </div>
            @endif

            @if ($order->payments->count())
                <div class="mt-4 space-y-2">
                    @foreach ($order->payments as $payment)
                        <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <span>{{ strtoupper($payment->gateway) }} · {{ $payment->transaction_id }}</span>
                                <span class="font-semibold capitalize {{ $payment->status === 'verified' ? 'text-emerald-700' : ($payment->status === 'rejected' ? 'text-rose-600' : 'text-amber-600') }}">{{ $payment->status }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">৳{{ number_format($payment->amount, 2) }} · {{ $payment->created_at->format('d M Y, h:i A') }}</p>
                            @if ($payment->rejection_reason)<p class="mt-1 text-xs text-rose-600">Reason: {{ $payment->rejection_reason }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
