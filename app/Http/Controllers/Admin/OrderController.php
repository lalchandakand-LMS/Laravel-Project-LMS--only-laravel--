<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\PaymentStatusNotification;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'branch'])->withCount('items')->latest();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($q = $request->get('q')) {
            $query->where(fn ($w) => $w->where('order_no', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")));
        }

        return view('admin.orders.index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'totals' => [
                'all' => Order::count(),
                'paid' => Order::where('status', 'paid')->count(),
                'pending' => Order::whereIn('status', ['pending', 'partially_paid'])->count(),
                'revenue' => Payment::where('status', 'verified')->sum('amount'),
            ],
        ]);
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', [
            'order' => $order->load(['items', 'payments.verifier', 'user', 'branch']),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate(['status' => 'required|in:pending,paid,failed,cancelled']);

        if ($order->status === 'paid' && $data['status'] !== 'paid') {
            return back()->with('error', 'A paid order cannot be downgraded. Use a recorded refund or adjustment workflow.');
        }

        if ($data['status'] === 'paid' && $order->status !== 'paid') {
            try {
                DB::transaction(function () use ($request, $order) {
                    $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                    $due = $lockedOrder->dueAmount();
                    if ($due > 0) {
                        Payment::create([
                            'order_id' => $lockedOrder->id,
                            'gateway' => 'admin_manual',
                            'transaction_id' => 'ADMIN-'.Str::upper(Str::random(12)),
                            'sender_number' => 'Recorded by '.$request->user()->name,
                            'amount' => $due,
                            'status' => 'verified',
                            'verified_by' => $request->user()->id,
                            'verified_at' => now(),
                        ]);
                    }
                    OrderService::fulfill($lockedOrder);
                });
            } catch (ValidationException $exception) {
                return back()->withErrors($exception->errors())
                    ->with('error', 'Course capacity has been reached. Order remains unpaid and requires manual review.');
            }
        } else {
            $order->update($data);
        }

        return back()->with('success', 'Order marked as '.$data['status'].'.');
    }

    public function verifyPayment(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'action' => 'required|in:verify,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string|max:2000',
        ]);

        try {
            DB::transaction(function () use ($request, $payment, $data) {
                $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $order = Order::whereKey($lockedPayment->order_id)->lockForUpdate()->firstOrFail();

                if ($lockedPayment->status !== 'pending') {
                    throw ValidationException::withMessages(['payment' => 'This payment has already been reviewed.']);
                }

                if ($data['action'] === 'reject') {
                    $lockedPayment->update([
                        'status' => 'rejected',
                        'verified_by' => $request->user()->id,
                        'verified_at' => now(),
                        'rejection_reason' => $data['rejection_reason'] ?? 'Payment could not be verified.',
                    ]);
                    $netPaid = $order->amountPaid();
                    $order->update([
                        'status' => $netPaid >= $order->total
                            ? 'paid'
                            : ($netPaid > 0 ? 'partially_paid' : 'pending'),
                    ]);
                    return;
                }

                $alreadyPaid = (float) $order->payments()->where('status', 'verified')->sum('amount');
                if (round($alreadyPaid + $lockedPayment->amount, 2) > round($order->total, 2)) {
                    throw ValidationException::withMessages(['payment' => 'Approving this payment would exceed the order balance. Review the amount before approval.']);
                }

                $lockedPayment->update([
                    'status' => 'verified',
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                    'rejection_reason' => null,
                ]);

                $newPaid = round($alreadyPaid + $lockedPayment->amount, 2);
                if ($newPaid >= round($order->total, 2)) {
                    OrderService::fulfill($order);
                } else {
                    $order->update(['status' => 'partially_paid']);
                }
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $payment->fresh()->load('order.user');
        $payment->order->user?->notify(new PaymentStatusNotification(
            $payment,
            $data['action'] === 'verify' ? 'verified' : 'rejected'
        ));

        return back()->with('success', $data['action'] === 'verify'
            ? 'Payment verified. The remaining balance has been recalculated.'
            : 'Payment rejected. The student can submit a corrected payment.');
    }

    /** Record cash or office collection received by an administrator. */
    public function recordOfflinePayment(Request $request, Order $order)
    {
        $data = $request->validate([
            'gateway' => 'required|in:cash,office',
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$order->dueAmount()],
            'reference' => 'nullable|string|max:80',
            'note' => 'nullable|string|max:1000',
        ]);

        $branch = $order->branch;
        abort_unless($branch && $branch->is_active, 403, 'The order branch is inactive.');
        abort_unless(in_array($data['gateway'], $branch->payment_methods ?? [], true), 403, 'This offline method is not enabled for the order branch.');

        try {
            DB::transaction(function () use ($request, $order, $data) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $alreadyPaid = $lockedOrder->amountPaid();
                if (round($alreadyPaid + (float) $data['amount'], 2) > round($lockedOrder->total, 2)) {
                    throw ValidationException::withMessages(['amount' => 'The amount exceeds the outstanding balance.']);
                }

                Payment::create([
                    'order_id' => $lockedOrder->id,
                    'gateway' => $data['gateway'],
                    'transaction_id' => $data['reference'] ?: 'OFFLINE-'.Str::upper(Str::random(12)),
                    'sender_number' => 'Received by '.$request->user()->name,
                    'amount' => $data['amount'],
                    'status' => 'verified',
                    'payload' => ['note' => $data['note'] ?? null],
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                ]);

                if (round($alreadyPaid + (float) $data['amount'], 2) >= round($lockedOrder->total, 2)) {
                    OrderService::fulfill($lockedOrder);
                } else {
                    $lockedOrder->update(['status' => 'partially_paid']);
                }
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $order->user?->notify(new PaymentStatusNotification(
            $order->payments()->latest()->first()->load('order'),
            'verified'
        ));

        return back()->with('success', 'Offline payment recorded in the ledger.');
    }

    public function paymentProof(Payment $payment): BinaryFileResponse
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);
        return response()->file(Storage::disk('local')->path($payment->proof_path));
    }
}
