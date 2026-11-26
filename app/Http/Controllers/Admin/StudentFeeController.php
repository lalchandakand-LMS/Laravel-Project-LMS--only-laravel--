<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachingAdmission;
use App\Models\StudentFeeCredit;
use App\Models\StudentFeeCreditApplication;
use App\Models\StudentFeeInvoice;
use App\Models\StudentFeePayment;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class StudentFeeController extends Controller
{
    public function index(Request $request)
    {
        $invoices = StudentFeeInvoice::with(['student', 'coachingAdmission', 'course', 'coachingBatch', 'branch', 'payments'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', '%'.$request->input('q').'%')->orWhere('email', 'like', '%'.$request->input('q').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('issued_at')->paginate(25)->withQueryString();

        $admissions = CoachingAdmission::with(['student', 'course', 'coachingBatch', 'branch'])
            ->where('status', 'admitted')->whereNotNull('student_id')->latest('admitted_at')->get();

        $invoiced = (float) StudentFeeInvoice::sum(DB::raw('amount - discount'));
        $collected = (float) StudentFeePayment::sum('amount');
        $applied = (float) StudentFeePayment::sum('applied_amount') + (float) DB::table('student_fee_credit_applications')->sum('amount');
        $totals = [
            'invoiced' => $invoiced,
            'collected' => $collected,
            'due' => max(0, round($invoiced - $applied, 2)),
            'advance' => max(0, (float) StudentFeeCredit::sum('amount') - (float) DB::table('student_fee_credit_applications')->sum('amount')),
        ];

        return view('admin.coaching-fees.index', compact('invoices', 'admissions', 'totals'));
    }

    public function storeInvoice(Request $request)
    {
        $data = $request->validate([
            'coaching_admission_id' => ['required', 'integer', Rule::exists('coaching_admissions', 'id')->where(fn ($q) => $q->where('status', 'admitted')->whereNotNull('student_id'))],
            'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'due_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($data) {
            $admission = CoachingAdmission::query()->whereKey($data['coaching_admission_id'])->lockForUpdate()->firstOrFail();
            abort_unless($admission->isActive(), 422, 'শুধু ভর্তি সম্পন্ন হওয়া coaching student-এর recurring invoice করা যাবে।');
            $discount = (float) ($data['discount'] ?? 0);
            abort_if($discount > (float) $data['amount'], 422, 'Discount invoice amount-এর বেশি হতে পারবে না।');

            $invoice = StudentFeeInvoice::create([
                'coaching_admission_id' => $admission->id,
                'student_id' => $admission->student_id,
                'coaching_batch_id' => $admission->coaching_batch_id,
                'course_id' => $admission->course_id,
                'branch_id' => $admission->branch_id,
                'invoice_type' => 'recurring',
                'description' => $data['description'],
                'issued_at' => now()->toDateString(),
                'due_at' => $data['due_at'] ?? null,
                'amount' => $data['amount'],
                'discount' => $discount,
                'status' => 'unpaid',
                'created_by' => auth()->id(),
            ]);

            $credits = StudentFeeCredit::query()->where('student_id', $admission->student_id)
                ->whereHas('payment.invoice', fn ($query) => $query->where('branch_id', $admission->branch_id))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($credits as $credit) {
                $toApply = min($credit->remainingAmount(), $invoice->dueAmount());
                if ($toApply <= 0) { continue; }
                StudentFeeCreditApplication::create([
                    'credit_id' => $credit->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $toApply,
                    'applied_by' => auth()->id(),
                ]);
                $invoice->refresh();
                if ($invoice->dueAmount() <= 0) { break; }
            }
            $invoice->syncStatus();
        });

        return back()->with('success', 'কোচিং ফি invoice তৈরি হয়েছে।');
    }

    public function collectPayment(Request $request, StudentFeeInvoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'method' => ['required', Rule::in(['cash', 'office', 'bank', 'bkash', 'nagad', 'rocket'])],
            'reference' => ['nullable', 'string', 'max:120', Rule::unique('student_fee_payments', 'reference')],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($invoice, $data) {
                $invoice = StudentFeeInvoice::query()->with('coachingAdmission')->lockForUpdate()->findOrFail($invoice->id);
                $admission = $invoice->coachingAdmission;
                abort_unless($admission && (int) $invoice->student_id === (int) $admission->student_id, 422, 'এটি coaching admission-এর invoice নয়।');
                abort_unless(in_array($admission->status, ['awaiting_payment', 'admitted'], true), 422, 'এই coaching admission-এ payment নেওয়া যাবে না।');
                abort_if($invoice->invoice_type !== 'admission' && ! $admission->isActive(), 422, 'শুধু সক্রিয় coaching admission-এর recurring fee নেওয়া যাবে।');
                abort_if($invoice->dueAmount() <= 0, 422, 'এই invoice ইতিমধ্যে সম্পূর্ণ পরিশোধ হয়েছে।');

                $amount = round((float) $data['amount'], 2);
                $applied = min($amount, $invoice->dueAmount());
                $advance = round($amount - $applied, 2);

                $payment = StudentFeePayment::create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                    'amount' => $amount,
                    'applied_amount' => $applied,
                    'advance_amount' => $advance,
                    'method' => $data['method'],
                    'reference' => $data['reference'] ?? null,
                    'note' => $data['note'] ?? null,
                    'collected_by' => auth()->id(),
                    'received_at' => now(),
                ]);

                if ($advance > 0) {
                    StudentFeeCredit::create([
                        'student_id' => $invoice->student_id,
                        'payment_id' => $payment->id,
                        'amount' => $advance,
                        'note' => 'Advance coaching fee credit from '.$payment->receipt_no,
                    ]);
                }

                $invoice->syncStatus();
                if ($invoice->invoice_type === 'admission' && $invoice->dueAmount() <= 0) {
                    $admission->activateAfterAdmissionFee();
                }
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicatePaymentReference($exception)) {
                throw ValidationException::withMessages([
                    'reference' => 'এই transaction reference দিয়ে payment আগেই record করা হয়েছে।',
                ]);
            }

            throw $exception;
        }

        return back()->with('success', 'পেমেন্ট রেকর্ড হয়েছে। ভর্তি ফি সম্পূর্ণ পরিশোধ হলে coaching enrollment সক্রিয় হবে; অতিরিক্ত টাকা advance credit হিসেবে থাকবে।');
    }

    private function isDuplicatePaymentReference(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'student_fee_payments_reference_unique')
            || str_contains($message, 'student_fee_payments.reference');
    }
}
