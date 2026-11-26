<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\User;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Enrollment;
use App\Models\EbookPurchase;
use App\Notifications\PaymentStatusNotification;
use App\Notifications\PaymentRefundNotification;
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
        $this->validateOrderFilters($request);
        $query = $this->ordersQuery($request);
        $totals = [
            'all' => Order::count(),
            'paid' => Order::where('status', 'paid')->count(),
            'pending' => Order::whereIn('status', ['pending', 'partially_paid'])->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'revenue' => max(0, (float) Payment::where('status', 'verified')->sum('amount')
                - (float) PaymentAdjustment::where('type', 'refund')->sum('amount')
                + (float) PaymentAdjustment::where('type', 'credit')->sum('amount')),
        ];

        return view('admin.orders.index', [
            'orders' => $query->paginate(25)->withQueryString(),
            'totals' => $totals,
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    private function validateOrderFilters(Request $request): void
    {
        $request->validate([
            'status' => 'nullable|in:pending,partially_paid,paid,failed,cancelled,refunded',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'item_type' => 'nullable|in:course,ebook',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'q' => 'nullable|string|max:120',
        ]);
    }

    private function ordersQuery(Request $request)
    {
        return Order::query()->with(['user', 'branch'])->withCount('items')
            ->withSum(['payments as verified_paid' => fn ($q) => $q->where('status', 'verified')], 'amount')
            ->withSum(['adjustments as refund_total' => fn ($q) => $q->where('type', 'refund')], 'amount')
            ->withSum(['adjustments as credit_total' => fn ($q) => $q->where('type', 'credit')], 'amount')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('item_type'), fn ($q) => $q->whereHas('items', fn ($item) => $item->where('item_type', $request->input('item_type'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->string('q')->toString());
                $q->where(fn ($w) => $w->where('order_no', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
            })
            ->latest();
    }

    public function export(Request $request)
    {
        $this->validateOrderFilters($request);
        $orders = $this->ordersQuery($request)->with(['items', 'payments', 'adjustments'])->get();

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Date', 'Student', 'Phone', 'Email', 'Branch', 'Items', 'Subtotal', 'Discount', 'Total', 'Verified received', 'Refunds', 'Due', 'Status']);
            foreach ($orders as $order) {
                $received = (float) $order->payments->where('status', 'verified')->sum('amount');
                $refunds = (float) $order->adjustments->where('type', 'refund')->sum('amount');
                $credits = (float) $order->adjustments->where('type', 'credit')->sum('amount');
                fputcsv($out, [
                    $order->order_no, optional($order->created_at)->toDateTimeString(),
                    $order->name, $order->phone, $order->email, $order->branch?->name,
                    $order->items->count(), $order->subtotal, $order->discount, $order->total,
                    number_format($received, 2, '.', ''), number_format($refunds, 2, '.', ''),
                    number_format(max(0, $order->total - $received + $refunds - $credits), 2, '.', ''), $order->status,
                ]);
            }
            fclose($out);
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create()
    {
        return view('admin.orders.form', [
            'order' => new Order(),
            'students' => User::where('role', 'student')->where('is_active', true)->orderBy('name')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'courses' => Course::where('is_published', true)->orderBy('title')->get(),
            'ebooks' => Ebook::where('is_published', true)->where('is_free', false)->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:30',
            'email' => 'required|email|max:150',
            'address' => 'nullable|string|max:2000',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:course,ebook',
            'items.*.id' => 'required|integer',
        ]);
        $student = User::where('role', 'student')->where('is_active', true)->findOrFail($data['user_id']);
        $branch = Branch::where('is_active', true)->findOrFail($data['branch_id']);
        if ($student->branch_id && (int) $student->branch_id !== $branch->id) {
            throw ValidationException::withMessages(['branch_id' => 'Select the student’s assigned branch.']);
        }

        $lines = [];
        foreach ($data['items'] as $line) {
            $product = $line['type'] === 'course'
                ? Course::where('is_published', true)->findOrFail($line['id'])
                : Ebook::where('is_published', true)->where('is_free', false)->findOrFail($line['id']);
            $price = $line['type'] === 'course' ? $product->final_price : (float) $product->price;
            if ($price <= 0) {
                throw ValidationException::withMessages(['items' => 'Free items should be enrolled or granted through the free access workflow, not an unpaid order.']);
            }
            $lines[] = ['type' => $line['type'], 'id' => $product->id, 'title' => $product->title, 'price' => $price];
        }

        $subtotal = round(array_sum(array_column($lines, 'price')), 2);
        $order = DB::transaction(function () use ($student, $branch, $data, $lines, $subtotal) {
            $order = Order::create([
                'user_id' => $student->id, 'branch_id' => $branch->id,
                'subtotal' => $subtotal, 'discount' => 0, 'total' => $subtotal,
                'status' => 'pending', 'name' => $data['name'], 'phone' => $data['phone'],
                'email' => $data['email'], 'address' => $data['address'] ?? null, 'note' => $data['note'] ?? null,
            ]);
            foreach ($lines as $line) {
                $order->items()->create([
                    'item_type' => $line['type'], 'item_id' => $line['id'],
                    'title' => $line['title'], 'price' => $line['price'], 'qty' => 1,
                ]);
            }
            return $order;
        });

        return redirect()->route('admin.orders.show', $order)->with('success', 'Order created. It is awaiting payment; no payment was recorded.');
    }

    public function edit(Order $order)
    {
        abort_if($order->payments()->exists(), 403, 'Orders with payment records cannot be edited. Use the payment/refund ledger.');
        return view('admin.orders.form', [
            'order' => $order->load('items'),
            'students' => collect(), 'branches' => collect(),
            'courses' => Course::where('is_published', true)->orderBy('title')->get(),
            'ebooks' => Ebook::where('is_published', true)->where('is_free', false)->orderBy('title')->get(),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        abort_if($order->payments()->exists(), 403, 'Orders with payment records cannot be edited. Use the payment/refund ledger.');
        $data = $request->validate([
            'name' => 'required|string|max:100', 'phone' => 'required|string|max:30',
            'email' => 'required|email|max:150', 'address' => 'nullable|string|max:2000',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:course,ebook',
            'items.*.id' => 'required|integer',
        ]);
        $lines = [];
        foreach ($data['items'] as $line) {
            $product = $line['type'] === 'course'
                ? Course::where('is_published', true)->findOrFail($line['id'])
                : Ebook::where('is_published', true)->where('is_free', false)->findOrFail($line['id']);
            $price = $line['type'] === 'course' ? $product->final_price : (float) $product->price;
            if ($price <= 0) {
                throw ValidationException::withMessages(['items' => 'Free items cannot be added to a paid order.']);
            }
            $lines[] = ['type' => $line['type'], 'id' => $product->id, 'title' => $product->title, 'price' => $price];
        }
        $subtotal = round(array_sum(array_column($lines, 'price')), 2);
        $customerData = collect($data)->except('items')->all();
        DB::transaction(function () use ($order, $customerData, $lines, $subtotal) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->payments()->exists()) {
                abort(403, 'Orders with payment records cannot be edited.');
            }
            $locked->items()->delete();
            foreach ($lines as $line) {
                $locked->items()->create([
                    'item_type' => $line['type'], 'item_id' => $line['id'],
                    'title' => $line['title'], 'price' => $line['price'], 'qty' => 1,
                ]);
            }
            $locked->update($customerData + ['subtotal' => $subtotal, 'discount' => 0, 'total' => $subtotal]);
        });
        return redirect()->route('admin.orders.show', $order)->with('success', 'Order details and items updated.');
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', [
            'order' => $order->load(['items', 'payments.verifier', 'adjustments.creator', 'user', 'branch']),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate(['status' => 'required|in:pending,paid,failed,cancelled']);

        if ($order->amountPaid() > 0 && $data['status'] !== 'paid' && $data['status'] !== $order->status) {
            return back()->with('error', 'An order with verified money cannot be downgraded outside the payment ledger. Record a refund or adjustment first.');
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
        if (! empty($data['reference']) && Payment::where('transaction_id', $data['reference'])->exists()) {
            throw ValidationException::withMessages(['reference' => 'This receipt/reference number is already recorded.']);
        }

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

    /** Record a refund as a separate immutable ledger adjustment. */
    public function recordRefund(Request $request, Order $order)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$order->amountPaid()],
            'payment_id' => 'nullable|integer|exists:payments,id',
            'reference' => 'nullable|string|max:100',
            'reason' => 'required|string|max:2000',
        ]);

        if (! empty($data['payment_id']) && ! $order->payments()->whereKey($data['payment_id'])->where('status', 'verified')->exists()) {
            throw ValidationException::withMessages(['payment_id' => 'Select a verified payment belonging to this order.']);
        }

        try {
            $adjustment = DB::transaction(function () use ($request, $order, $data) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $netPaid = $lockedOrder->amountPaid();
                if ((float) $data['amount'] > $netPaid) {
                    throw ValidationException::withMessages(['amount' => 'Refund cannot exceed the verified net amount received.']);
                }

                if (! empty($data['reference']) && PaymentAdjustment::where('reference', $data['reference'])->exists()) {
                    throw ValidationException::withMessages(['reference' => 'This refund reference has already been recorded.']);
                }

                $adjustment = PaymentAdjustment::create([
                    'order_id' => $lockedOrder->id,
                    'payment_id' => $data['payment_id'] ?? null,
                    'created_by' => $request->user()->id,
                    'type' => 'refund',
                    'amount' => $data['amount'],
                    'reference' => $data['reference'] ?? null,
                    'reason' => $data['reason'],
                ]);

                $netAfterRefund = $lockedOrder->amountPaid();
                if ($netAfterRefund < $lockedOrder->total) {
                    Enrollment::where('order_id', $lockedOrder->id)
                        ->where('status', 'active')
                        ->update(['status' => 'suspended']);
                    EbookPurchase::where('order_id', $lockedOrder->id)->delete();

                    $lockedOrder->update([
                        'status' => $netAfterRefund > 0 ? 'partially_paid' : 'refunded',
                    ]);
                }

                return $adjustment;
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $adjustment->load('order.user');
        $adjustment->order->user?->notify(new PaymentRefundNotification($adjustment));

        return back()->with('success', 'Refund recorded. The ledger and outstanding balance have been updated.');
    }

    public function paymentProof(Payment $payment): BinaryFileResponse
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);
        return response()->file(Storage::disk('local')->path($payment->proof_path));
    }
}
