<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use Illuminate\Http\Request;

class PaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $payments = $this->paymentsQuery($request)->latest()->paginate(25)->withQueryString();
        $adjustments = $this->adjustmentsQuery($request)->latest()->paginate(15, ['*'], 'adjustments_page')->withQueryString();

        $gross = (float) $this->paymentsQuery($request)->where('status', 'verified')->sum('amount');
        $refunds = (float) $this->adjustmentsQuery($request)->where('type', 'refund')->sum('amount');

        return view('admin.payments.report', [
            'payments' => $payments,
            'adjustments' => $adjustments,
            'branches' => Branch::orderBy('name')->get(),
            'totals' => [
                'gross' => $gross,
                'refunds' => $refunds,
                'net' => max(0, round($gross - $refunds, 2)),
                'pending' => $this->paymentsQuery($request)->where('status', 'pending')->count(),
                'verified' => $this->paymentsQuery($request)->where('status', 'verified')->count(),
                'rejected' => $this->paymentsQuery($request)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $payments = $this->paymentsQuery($request)->with(['order.branch', 'verifier'])->oldest()->get();
        $adjustments = $this->adjustmentsQuery($request)->with(['order.branch', 'creator'])->oldest()->get();

        return response()->streamDownload(function () use ($payments, $adjustments) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Record type', 'Order', 'Branch', 'Method', 'Reference', 'Amount', 'Status', 'Reviewed/Created by', 'Reason']);

            foreach ($payments as $payment) {
                fputcsv($output, [
                    optional($payment->created_at)->toDateTimeString(),
                    'payment',
                    $payment->order?->order_no,
                    $payment->order?->branch?->name,
                    $payment->gateway,
                    $payment->transaction_id,
                    number_format($payment->amount, 2, '.', ''),
                    $payment->status,
                    $payment->verifier?->name,
                    $payment->rejection_reason,
                ]);
            }

            foreach ($adjustments as $adjustment) {
                fputcsv($output, [
                    optional($adjustment->created_at)->toDateTimeString(),
                    $adjustment->type,
                    $adjustment->order?->order_no,
                    $adjustment->order?->branch?->name,
                    'adjustment',
                    $adjustment->reference,
                    number_format($adjustment->amount, 2, '.', ''),
                    'recorded',
                    $adjustment->creator?->name,
                    $adjustment->reason,
                ]);
            }

            fclose($output);
        }, 'payment-ledger-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function paymentsQuery(Request $request)
    {
        return Payment::with(['order.user', 'order.branch', 'verifier'])
            ->when($request->filled('gateway'), fn ($q) => $q->where('gateway', $request->string('gateway')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('order', fn ($order) => $order->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));
    }

    private function adjustmentsQuery(Request $request)
    {
        return PaymentAdjustment::with(['order.user', 'order.branch', 'creator'])
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('order', fn ($order) => $order->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));
    }
}
