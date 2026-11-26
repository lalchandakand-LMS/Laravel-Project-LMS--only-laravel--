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
        $this->validateFilters($request);
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
        $this->validateFilters($request);
        $payments = $this->paymentsQuery($request)->with(['order.branch', 'verifier'])->reorder('id');
        $adjustments = $this->adjustmentsQuery($request)->with(['order.branch', 'creator'])->reorder('id');

        return response()->streamDownload(function () use ($payments, $adjustments) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Record type', 'Order', 'Branch', 'Method', 'Reference', 'Amount', 'Status', 'Reviewed/Created by', 'Reason']);

            foreach ($payments->lazyById(500) as $payment) {
                fputcsv($output, [
                    optional($payment->created_at)->toDateTimeString(),
                    $this->safeCsvCell('payment'),
                    $this->safeCsvCell($payment->order?->order_no),
                    $this->safeCsvCell($payment->order?->branch?->name),
                    $this->safeCsvCell($payment->gateway),
                    $this->safeCsvCell($payment->transaction_id),
                    number_format($payment->amount, 2, '.', ''),
                    $this->safeCsvCell($payment->status),
                    $this->safeCsvCell($payment->verifier?->name),
                    $this->safeCsvCell($payment->rejection_reason),
                ]);
            }

            foreach ($adjustments->lazyById(500) as $adjustment) {
                fputcsv($output, [
                    optional($adjustment->created_at)->toDateTimeString(),
                    $this->safeCsvCell($adjustment->type),
                    $this->safeCsvCell($adjustment->order?->order_no),
                    $this->safeCsvCell($adjustment->order?->branch?->name),
                    $this->safeCsvCell('adjustment'),
                    $this->safeCsvCell($adjustment->reference),
                    number_format($adjustment->amount, 2, '.', ''),
                    $this->safeCsvCell('recorded'),
                    $this->safeCsvCell($adjustment->creator?->name),
                    $this->safeCsvCell($adjustment->reason),
                ]);
            }

            fclose($output);
        }, 'payment-ledger-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function safeCsvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[\\x00-\\x20]*[=+\\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'gateway' => 'nullable|in:bkash,nagad,rocket,bank,cash,office,admin_manual',
            'status' => 'nullable|in:pending,verified,rejected',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
    }

    private function paymentsQuery(Request $request)
    {
        return Payment::with(['order.user', 'order.branch', 'verifier'])
            ->when($request->filled('gateway'), fn ($q) => $q->where('gateway', $request->input('gateway')))
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
