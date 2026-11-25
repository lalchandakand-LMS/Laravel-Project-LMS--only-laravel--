<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Http\Request;
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
        $branches = Branch::active()->orderBy('name')->get();

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
            'order' => $order->load(['items', 'payments.verifier', 'branch', 'user']),
        ]);
    }

    /** Student submits a full or partial manual payment for branch-approved verification. */
    public function pay(Request $request, Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        abort_unless(in_array($order->status, ['pending', 'partially_paid'], true), 403, 'This order is not awaiting payment.');

        $branch = $order->branch;
        abort_unless($branch && $branch->is_active, 403, 'The selected branch is no longer accepting payments.');

        $enabledMethods = $branch->enabledStudentPaymentMethods();
        if ($enabledMethods === []) {
            throw ValidationException::withMessages(['gateway' => 'No online payment method is enabled for this branch.']);
        }

        if ($order->dueAmount() <= 0) {
            return back()->with('info', 'This order has no outstanding balance.');
        }

        if ($order->payments()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['payment' => 'A payment is already awaiting verification for this order. Please wait for the branch to review it.']);
        }

        $data = $request->validate([
            'gateway' => ['required', 'string', 'in:'.implode(',', $enabledMethods)],
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$order->dueAmount()],
            'sender_number' => 'required|string|max:80',
            'transaction_id' => 'required|string|max:80',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
        ]);

        if ($data['gateway'] === 'bank' && ! $request->hasFile('proof')) {
            throw ValidationException::withMessages(['proof' => 'Please upload the bank deposit slip or transfer receipt.']);
        }

        if (Payment::where('transaction_id', $data['transaction_id'])->whereNotNull('transaction_id')->exists()) {
            throw ValidationException::withMessages(['transaction_id' => 'This transaction ID has already been submitted.']);
        }

        $proofPath = $request->file('proof')?->store('payment-proofs', 'local');

        Payment::create([
            'order_id' => $order->id,
            'gateway' => $data['gateway'],
            'amount' => $data['amount'],
            'sender_number' => $data['sender_number'],
            'transaction_id' => $data['transaction_id'],
            'proof_path' => $proofPath,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Payment submitted for verification. Your remaining balance and course access will update after approval.');
    }
}
