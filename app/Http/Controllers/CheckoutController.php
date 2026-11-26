<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index()
    {
        $totals = CartController::totals();
        if ($totals['count'] === 0) {
            return redirect()->route('cart.index')->with('info', 'Your cart is empty.');
        }

        $assignedBranch = $totals['count'] ? auth()->user()->branch : null;
        $branches = Branch::active()->orderBy('name')->get()
            ->filter(fn (Branch $branch) => $branch->enabledStudentPaymentMethods() !== [])
            ->values();

        return view('cart.checkout', $totals + compact('branches', 'assignedBranch'));
    }

    public function store(Request $request)
    {
        abort_if(CartController::totals()['count'] === 0, 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:150',
            'address' => collect(CartController::totals()['items'])->contains('type', 'product') ? 'required|string|max:1000' : 'nullable|string|max:1000',
            'note' => 'nullable|string|max:255',
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        $branch = Branch::active()->findOrFail($data['branch_id']);
        if ($branch->enabledStudentPaymentMethods() === []) {
            throw ValidationException::withMessages(['branch_id' => 'This branch has not enabled any payment method yet.']);
        }
        if (auth()->user()->branch_id && auth()->user()->branch_id !== $branch->id) {
            throw ValidationException::withMessages(['branch_id' => 'Please select your assigned branch.']);
        }

        // Lock the coupon row while reserving its usage in the order. This serializes
        // concurrent checkouts so global and per-student limits cannot be exceeded.
        $order = DB::transaction(function () use ($request, $data, $branch) {
            $lockedCoupon = session('coupon')
                ? Coupon::where('code', session('coupon'))->lockForUpdate()->first()
                : null;
            $totals = CartController::totals($branch->id, $lockedCoupon);

            if (session('coupon') && ! $totals['coupon']) {
                throw ValidationException::withMessages([
                    'coupon' => 'The coupon is no longer valid for this branch, cart, or usage limit. Remove it and try again.',
                ]);
            }

            if ($totals['count'] === 0) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            foreach ($totals['items'] as $cartItem) {
                if ($cartItem['type'] !== 'product') {
                    continue;
                }

                $stockItem = Product::whereKey($cartItem['id'])
                    ->where('is_published', true)
                    ->lockForUpdate()
                    ->first();

                if (! $stockItem || $stockItem->stock_quantity < (int) ($cartItem['qty'] ?? 1)) {
                    throw ValidationException::withMessages([
                        'cart' => 'The available stock for '.$cartItem['title'].' has changed. Update your cart before placing the order.',
                    ]);
                }
            }

            $order = Order::create($data + [
                'user_id' => $request->user()->id,
                'coupon_id' => $totals['coupon']?->id,
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
                'status' => 'pending',
            ]);

            foreach ($totals['items'] as $item) {
                $order->items()->create([
                    'item_type' => $item['type'],
                    'item_id' => $item['id'],
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'qty' => (int) ($item['qty'] ?? 1),
                ]);
            }

            return $order;
        });

        if ($order->total <= 0) {
            try {
                OrderService::fulfill($order);
            } catch (ValidationException $exception) {
                // A free order that cannot be fulfilled must release its coupon reservation.
                $order->update(['status' => 'failed']);

                return redirect()->route('cart.index')
                    ->withErrors($exception->errors())
                    ->with('error', 'A course has reached its seat limit. Your order was not charged; please review your cart.');
            }
        }

        session()->forget(['cart', 'cart_quantities', 'coupon']);

        return redirect()->route('checkout.success', $order->order_no);
    }

    public function success(Order $order)
    {
        abort_unless($order->user_id === auth()->id() || auth()->user()->isAdmin(), 403);

        $branch = $order->branch;
        $enabledMethods = $branch?->enabledStudentPaymentMethods() ?? [];

        return view('cart.success', [
            'order' => $order->load('items', 'payments'),
            'branch' => $branch,
            'enabledMethods' => $enabledMethods,
            'paymentNumbers' => [
                'bkash' => \App\Models\Setting::get('bkash_number', ''),
                'nagad' => \App\Models\Setting::get('nagad_number', ''),
            ],
        ]);
    }

    public function receipt(Order $order)
    {
        abort_unless($order->user_id === auth()->id() || auth()->user()->isAdmin(), 403);

        return view('cart.receipt', [
            'order' => $order->load(['items', 'payments.verifier', 'adjustments.creator', 'branch', 'user']),
        ]);
    }

    /** Student submits a full or partial manual payment for branch-approved verification. */
    public function pay(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        // Preserve clear branch/payment errors before validating the submitted amount;
        // all of these conditions are rechecked under the order lock below.
        $preflightBranch = $order->branch;
        abort_unless($preflightBranch && $preflightBranch->is_active, 403, 'The selected branch is no longer accepting payments.');
        $preflightMethods = $preflightBranch->enabledStudentPaymentMethods();
        if ($preflightMethods === []) {
            throw ValidationException::withMessages(['gateway' => 'No online payment method is enabled for this branch.']);
        }

        $data = $request->validate([
            'gateway' => ['required', 'string', 'in:'.implode(',', $preflightMethods)],
            'amount' => ['required', 'numeric', 'min:1'],
            'sender_number' => 'required|string|max:80',
            'transaction_id' => 'required|string|max:80',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
        ]);

        if ($request->file('proof') && ! in_array(strtolower($request->file('proof')->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'pdf', 'webp'], true)) {
            throw ValidationException::withMessages(['proof' => 'Unsupported payment proof file type.']);
        }

        $proofPath = null;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $order, $data, &$proofPath) {
                // Serialize submissions for this order so simultaneous requests cannot
                // both pass the "one pending payment" and outstanding-balance checks.
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->user_id !== $request->user()->id) {
                    abort(403);
                }
                if (! in_array($lockedOrder->status, ['pending', 'partially_paid'], true)) {
                    throw ValidationException::withMessages(['payment' => 'This order is not awaiting payment.']);
                }

                $branch = Branch::whereKey($lockedOrder->branch_id)->where('is_active', true)->first();
                if (! $branch) {
                    throw ValidationException::withMessages(['gateway' => 'The selected branch is no longer accepting payments.']);
                }

                $enabledMethods = $branch->enabledStudentPaymentMethods();
                if (! in_array($data['gateway'], $enabledMethods, true)) {
                    throw ValidationException::withMessages(['gateway' => 'This payment method is not enabled for the order branch.']);
                }

                $due = $lockedOrder->dueAmount();
                if ($due <= 0) {
                    throw ValidationException::withMessages(['amount' => 'This order has no outstanding balance.']);
                }
                if ((float) $data['amount'] > $due) {
                    throw ValidationException::withMessages(['amount' => 'The payment amount exceeds the current outstanding balance.']);
                }

                if ($lockedOrder->payments()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['payment' => 'A payment is already awaiting verification for this order. Please wait for the branch to review it.']);
                }

                if ($data['gateway'] === 'bank' && ! $request->hasFile('proof')) {
                    throw ValidationException::withMessages(['proof' => 'Please upload the bank deposit slip or transfer receipt.']);
                }

                if (Payment::where('transaction_id', $data['transaction_id'])->exists()) {
                    throw ValidationException::withMessages(['transaction_id' => 'This transaction ID has already been submitted.']);
                }

                if ($request->hasFile('proof')) {
                    $proofPath = $request->file('proof')->store('payment-proofs', 'local');

                    if (! is_string($proofPath) || $proofPath === '') {
                        throw ValidationException::withMessages([
                            'proof' => 'The payment proof could not be stored. Please try again.',
                        ]);
                    }
                }

                Payment::create([
                    'order_id' => $lockedOrder->id,
                    'gateway' => $data['gateway'],
                    'amount' => $data['amount'],
                    'sender_number' => $data['sender_number'],
                    'transaction_id' => $data['transaction_id'],
                    'proof_path' => $proofPath,
                    'status' => 'pending',
                ]);
            });
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            if ($exception instanceof QueryException && $this->isTransactionReferenceConflict($exception)) {
                throw ValidationException::withMessages([
                    'transaction_id' => 'This transaction ID has already been submitted.',
                ]);
            }

            throw $exception;
        }

        return back()->with('success', 'Payment submitted for verification. Your remaining balance and course access will update after approval.');
    }

    private function isTransactionReferenceConflict(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'payments_transaction_id_unique')
            || str_contains($message, 'payments.transaction_id');
    }

}
