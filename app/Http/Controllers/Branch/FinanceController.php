<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\StudentFeeController as AdminStudentFeeController;
use App\Models\Branch as BranchModel;
use App\Models\CoachingAdmission;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StudentFeeInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    private function branch(): BranchModel
    {
        $user = auth()->user();
        $branch = $user->isBranchManager()
            ? BranchModel::forManager($user)
            : ($user->branch ?? null);

        abort_unless($branch, 403, 'No branch is assigned to this account.');
        if ($user->isBranchManager() && $user->branch_id && (int) $user->branch_id !== (int) $branch->id) {
            abort(403, 'Your branch assignment is inconsistent. Please contact the admin.');
        }

        return $branch;
    }

    public function index()
    {
        $branch = $this->branch();
        $orders = Order::where('branch_id', $branch->id)
            ->with(['user', 'payments', 'adjustments'])
            ->latest()->paginate(15, ['*'], 'orders_page');

        $pendingPayments = Payment::with(['order.user'])
            ->where('status', 'pending')
            ->whereHas('order', fn ($order) => $order->where('branch_id', $branch->id))
            ->latest()->paginate(15, ['*'], 'payments_page');

        $invoices = StudentFeeInvoice::with(['student', 'coachingAdmission', 'coachingBatch', 'payments', 'creditApplications'])
            ->where('branch_id', $branch->id)
            ->latest('issued_at')->paginate(15, ['*'], 'invoices_page');

        $admissions = CoachingAdmission::where('branch_id', $branch->id)
            ->where('status', 'admitted')
            ->with(['student', 'coachingBatch'])
            ->latest()->get();

        // Aggregate financial totals in SQL instead of loading every invoice/order
        // and its ledger rows into PHP. This keeps the dashboard bounded as branches grow.
        $invoicePayments = DB::table('student_fee_payments')
            ->select('invoice_id')
            ->selectRaw('SUM(applied_amount) as paid_total')
            ->groupBy('invoice_id');
        $invoiceCredits = DB::table('student_fee_credit_applications')
            ->select('invoice_id')
            ->selectRaw('SUM(amount) as credit_total')
            ->groupBy('invoice_id');
        $invoiceTotals = DB::table('student_fee_invoices as invoices')
            ->leftJoinSub($invoicePayments, 'fee_payments', fn ($join) => $join->on('fee_payments.invoice_id', '=', 'invoices.id'))
            ->leftJoinSub($invoiceCredits, 'fee_credits', fn ($join) => $join->on('fee_credits.invoice_id', '=', 'invoices.id'))
            ->where('invoices.branch_id', $branch->id)
            ->selectRaw('COALESCE(SUM(COALESCE(fee_payments.paid_total, 0) + COALESCE(fee_credits.credit_total, 0)), 0) as collected')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.amount - invoices.discount - COALESCE(fee_payments.paid_total, 0) - COALESCE(fee_credits.credit_total, 0) > 0 THEN invoices.amount - invoices.discount - COALESCE(fee_payments.paid_total, 0) - COALESCE(fee_credits.credit_total, 0) ELSE 0 END), 0) as due')
            ->first();

        $coachingCollected = (float) ($invoiceTotals->collected ?? 0);
        $coachingDue = (float) ($invoiceTotals->due ?? 0);

        $verifiedPayments = DB::table('payments')
            ->select('order_id')
            ->where('status', 'verified')
            ->selectRaw('SUM(amount) as received_total')
            ->groupBy('order_id');
        $orderAdjustments = DB::table('payment_adjustments')
            ->select('order_id')
            ->selectRaw("SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END) as refund_total")
            ->selectRaw("SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as credit_total")
            ->groupBy('order_id');
        $orderDue = DB::table('orders')
            ->leftJoinSub($verifiedPayments, 'verified_payments', fn ($join) => $join->on('verified_payments.order_id', '=', 'orders.id'))
            ->leftJoinSub($orderAdjustments, 'order_adjustments', fn ($join) => $join->on('order_adjustments.order_id', '=', 'orders.id'))
            ->where('orders.branch_id', $branch->id)
            ->whereIn('orders.status', ['pending', 'partially_paid'])
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.total - COALESCE(verified_payments.received_total, 0) + COALESCE(order_adjustments.refund_total, 0) - COALESCE(order_adjustments.credit_total, 0) > 0 THEN orders.total - COALESCE(verified_payments.received_total, 0) + COALESCE(order_adjustments.refund_total, 0) - COALESCE(order_adjustments.credit_total, 0) ELSE 0 END), 0) as due')
            ->value('due');

        $totals = [
            'collected' => $branch->revenue(),
            'order_due' => (float) $orderDue,
            'coaching_collected' => (float) $coachingCollected,
            'coaching_due' => (float) $coachingDue,
            'pending_verification' => Payment::where('status', 'pending')
                ->whereHas('order', fn ($order) => $order->where('branch_id', $branch->id))->count(),
        ];

        return view('branch.finance.index', compact('branch', 'orders', 'pendingPayments', 'invoices', 'admissions', 'totals'));
    }

    public function verifyPayment(Request $request, Payment $payment)
    {
        $branch = $this->branch();
        abort_unless((int) $payment->order?->branch_id === (int) $branch->id, 404);

        return app(AdminOrderController::class)->verifyPayment($request, $payment);
    }

    public function recordOfflinePayment(Request $request, Order $order)
    {
        $branch = $this->branch();
        abort_unless((int) $order->branch_id === (int) $branch->id, 404);

        return app(AdminOrderController::class)->recordOfflinePayment($request, $order);
    }

    public function createInvoice(Request $request)
    {
        $branch = $this->branch();
        $data = $request->validate([
            'coaching_admission_id' => ['required', 'integer', 'exists:coaching_admissions,id'],
        ]);
        CoachingAdmission::where('branch_id', $branch->id)
            ->where('status', 'admitted')
            ->findOrFail($data['coaching_admission_id']);

        return app(AdminStudentFeeController::class)->storeInvoice($request);
    }

    public function collectCoachingFee(Request $request, StudentFeeInvoice $invoice)
    {
        $branch = $this->branch();
        abort_unless((int) $invoice->branch_id === (int) $branch->id, 404);

        if (! is_string($request->input('method')) || ! in_array($request->input('method'), $branch->payment_methods ?? [], true)) {
            throw ValidationException::withMessages([
                'method' => 'This payment method is not enabled for your branch.',
            ]);
        }

        return app(AdminStudentFeeController::class)->collectPayment($request, $invoice);
    }
}
