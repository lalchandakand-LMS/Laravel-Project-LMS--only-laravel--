@extends('layouts.admin')
@section('title', 'Order '.$order->order_no)
@section('heading', 'Order #'.$order->order_no)
@section('content')
<div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900">Items</h3>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $order->statusBadge() }}">{{ strtoupper($order->status) }}</span>
            </div>
            <table class="mt-4 w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="py-3"><span class="mr-2 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase text-slate-500">{{ $item->item_type }}</span>{{ $item->title }}</td>
                            <td class="py-3 text-right font-semibold">৳{{ number_format($item->price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>৳{{ number_format($order->subtotal, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd>−৳{{ number_format($order->discount, 2) }}</dd></div>
                <div class="flex justify-between font-extrabold"><dt>Total</dt><dd>৳{{ number_format($order->total, 2) }}</dd></div>
                <div class="flex justify-between font-bold text-emerald-700"><dt>Verified received</dt><dd>৳{{ number_format($order->amountPaid(), 2) }}</dd></div>
                <div class="flex justify-between font-bold text-rose-700"><dt>Remaining due</dt><dd>৳{{ number_format($order->dueAmount(), 2) }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Payment verification ledger</h3>
            <div class="mt-3 space-y-3 text-sm">
                @forelse ($order->payments as $payment)
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold uppercase text-slate-700">{{ $payment->gateway }} · {{ $payment->transaction_id }}</p>
                                <p class="text-xs text-slate-500">From/received by: {{ $payment->sender_number ?: '—' }} · ৳{{ number_format($payment->amount, 2) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Submitted {{ $payment->created_at->format('d M Y, h:i A') }}
                                    @if ($payment->verifier) · Reviewed by {{ $payment->verifier->name }} at {{ optional($payment->verified_at)->format('d M Y, h:i A') }} @endif
                                </p>
                            </div>
                            <span class="ml-auto rounded-full px-2 py-0.5 text-xs font-bold
                                {{ $payment->status === 'verified' ? 'bg-emerald-100 text-emerald-700' : ($payment->status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $payment->status }}
                            </span>
                        </div>
                        @if ($payment->proof_path)
                            <a href="{{ route('admin.payments.proof', $payment) }}" target="_blank" rel="noopener" class="mt-2 inline-block text-xs font-bold text-emerald-700 underline">View uploaded deposit slip / receipt</a>
                        @endif
                        @if ($payment->rejection_reason)
                            <p class="mt-2 rounded-lg bg-rose-50 p-2 text-xs text-rose-700">Rejection reason: {{ $payment->rejection_reason }}</p>
                        @endif
                        @if ($payment->status === 'pending')
                            <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_auto]">
                                @csrf
                                <input name="rejection_reason" placeholder="Reason if rejecting (required for Reject)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
                                <button name="action" value="verify" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Verify payment</button>
                                <button name="action" value="reject" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white">Reject</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-slate-500">No payment submitted yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <div class="flex justify-end">
            <a href="{{ route('orders.receipt', $order->order_no) }}" target="_blank" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-emerald-700">View receipt</a>
        </div>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Customer</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Name</dt><dd class="font-semibold">{{ $order->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd class="font-semibold">{{ $order->phone }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="font-semibold">{{ $order->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Branch</dt><dd class="font-semibold">{{ $order->branch?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Account</dt><dd class="font-semibold"><a href="{{ route('admin.users.show', $order->user_id) }}" class="text-emerald-700">{{ optional($order->user)->name }}</a></dd></div>
            </dl>
        </section>

        @if ($order->dueAmount() > 0 && $order->branch && $order->branch->is_active && count(array_intersect(['cash', 'office'], $order->branch->payment_methods ?? [])))
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-bold text-slate-900">Record cash / office collection</h3>
                <p class="mt-1 text-xs text-slate-500">For money physically received by authorized office staff. The entry is immediately marked verified and added to the ledger.</p>
                <form method="POST" action="{{ route('admin.orders.offline-payment', $order->order_no) }}" class="mt-3 space-y-3">
                    @csrf
                    <select name="gateway" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @if (in_array('cash', $order->branch->payment_methods ?? [], true))<option value="cash">Cash</option>@endif
                        @if (in_array('office', $order->branch->payment_methods ?? [], true))<option value="office">Office payment</option>@endif
                    </select>
                    <input type="number" name="amount" min="1" max="{{ $order->dueAmount() }}" step="0.01" required placeholder="Amount received (due ৳{{ number_format($order->dueAmount(), 2) }})" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="reference" placeholder="Receipt / reference number (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <textarea name="note" rows="2" placeholder="Internal note (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    <button class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Record received payment</button>
                </form>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Change status</h3>
            <form method="POST" action="{{ route('admin.orders.status', $order->order_no) }}" class="mt-3 space-y-3">
                @csrf
                <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    @foreach (['pending', 'paid', 'failed', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Update order</button>
                <p class="text-xs text-slate-500">Marking an order as paid creates a corresponding manual ledger entry and grants access.</p>
            </form>
        </section>
    </div>
</div>
@endsection
