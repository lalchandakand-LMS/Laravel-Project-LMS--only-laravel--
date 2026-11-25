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

    /** Student submits a manual payment for branch-approved verification. */
    public function pay(Request $request, Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        abort_unless($order->status === 'pending', 403, 'This order is not awaiting payment.');

        $branch = $order->branch;
        abort_unless($branch && $branch->is_active, 403, 'The selected branch is no longer accepting payments.');

        $enabledMethods = $branch->enabledStudentPaymentMethods();
        $data = $request->validate([
            'gateway' => ['required', 'string', 'in:'.implode(',', $enabledMethods)],
            'sender_number' => 'required|string|max:30',
            'transaction_id' => 'required|string|max:60',
        ]);

        if (Payment::where('transaction_id', $data['transaction_id'])->whereNotNull('transaction_id')->exists()) {
            throw ValidationException::withMessages(['transaction_id' => 'This transaction ID has already been submitted.']);
        }

        Payment::create($data + [
            'order_id' => $order->id,
            'amount' => $order->total,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Payment submitted for verification. Your access will be enabled after approval.');
    }
}
