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

        $invoiceTotals = StudentFeeInvoice::with(['payments', 'creditApplications'])
            ->where('branch_id', $branch->id)->get();

        $coachingCollected = $invoiceTotals->sum(fn ($invoice) => $invoice->paidAmount());
        $coachingDue = $invoiceTotals->sum(fn ($invoice) => $invoice->dueAmount());
        $orderDue = Order::where('branch_id', $branch->id)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->get()->sum(fn (Order $order) => $order->dueAmount());

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
