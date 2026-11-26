<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
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
        $totals = CartController::totals();
        abort_if($totals['count'] === 0, 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:150',
            'address' => 'nullable|string|max:255',
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
                'qty' => 1,
            ]);
        }

        if ($totals['coupon']) {
            $totals['coupon']->increment('used_count');
        }

        if ($order->total <= 0) {
            try {
                OrderService::fulfill($order);
            } catch (ValidationException $exception) {
                return redirect()->route('cart.index')
                    ->withErrors($exception->errors())
                    ->with('error', 'A course has reached its seat limit. Your order was not charged; please review your cart.');
            }
        }

        session()->forget(['cart', 'coupon']);

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

        $data = $request->validate([
            'gateway' => ['required', 'string', 'max:30'],
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
            DB::transaction(function () use ($request, $order, $data, &$proofPath) {
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

                $proofPath = $request->file('proof')?->store('payment-proofs', 'local');

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
