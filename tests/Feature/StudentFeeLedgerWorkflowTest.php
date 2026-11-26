<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\CoachingBatch;
use App\Models\Course;
use App\Models\StudentFeeCredit;
use App\Models\StudentFeeCreditApplication;
use App\Models\StudentFeeInvoice;
use App\Models\StudentFeePayment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentFeeLedgerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupInvoice(float $amount = 1000): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Fee Ledger Branch',
            'code' => 'FEE-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'payment_methods' => ['cash', 'office', 'bank', 'bkash', 'nagad', 'rocket'],
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $course = Course::create([
            'title' => 'Fee Ledger English',
            'slug' => 'fee-ledger-'.Str::lower(Str::random(6)),
            'price' => 1000,
            'is_published' => true,
        ]);
        $batch = CoachingBatch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => 'Fee Ledger Morning',
            'code' => 'FEE-BATCH-'.Str::upper(Str::random(5)),
            'admission_fee' => 500,
            'monthly_fee' => 1500,
            'status' => 'ongoing',
        ]);
        $admission = CoachingAdmission::create([
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '01700000000',
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'coaching_batch_id' => $batch->id,
            'status' => 'admitted',
            'admitted_at' => now(),
        ]);
        $invoice = StudentFeeInvoice::create([
            'coaching_admission_id' => $admission->id,
            'student_id' => $student->id,
            'coaching_batch_id' => $batch->id,
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'recurring',
            'description' => 'Monthly coaching fee',
            'issued_at' => now()->toDateString(),
            'amount' => $amount,
            'discount' => 0,
            'status' => 'unpaid',
            'created_by' => $admin->id,
        ]);

        return [$admin, $branch, $student, $course, $batch, $admission, $invoice];
    }

    public function test_duplicate_fee_payment_reference_is_rejected_without_collecting_twice(): void
    {
        [$admin, $branch, $student, $course, $batch, $admission, $invoice] = $this->setupInvoice();

        $this->actingAs($admin)->post(route('admin.coaching-fees.payments.store', $invoice), [
            'amount' => 200,
            'method' => 'bank',
            'reference' => 'BANK-LEDGER-REF-001',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('admin.coaching-fees.payments.store', $invoice), [
            'amount' => 200,
            'method' => 'bkash',
            'reference' => 'BANK-LEDGER-REF-001',
        ])->assertRedirect()->assertSessionHasErrors('reference');

        $this->assertSame(1, StudentFeePayment::where('reference', 'BANK-LEDGER-REF-001')->count());
        $this->assertSame(800.0, $invoice->fresh()->dueAmount());
        $this->assertSame('partial', $invoice->fresh()->status);
    }

    public function test_database_rejects_duplicate_fee_payment_references_even_outside_the_controller(): void
    {
        [$admin, $branch, $student, $course, $batch, $admission, $invoice] = $this->setupInvoice();
        $secondInvoice = StudentFeeInvoice::create([
            'coaching_admission_id' => $admission->id,
            'student_id' => $student->id,
            'coaching_batch_id' => $batch->id,
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'recurring',
            'description' => 'Second monthly coaching fee',
            'issued_at' => now()->toDateString(),
            'amount' => 500,
            'discount' => 0,
            'status' => 'unpaid',
            'created_by' => $admin->id,
        ]);

        StudentFeePayment::create([
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'amount' => 100,
            'applied_amount' => 100,
            'advance_amount' => 0,
            'method' => 'bank',
            'reference' => 'GLOBAL-FEE-REFERENCE',
            'collected_by' => $admin->id,
            'received_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        StudentFeePayment::create([
            'invoice_id' => $secondInvoice->id,
            'student_id' => $student->id,
            'amount' => 100,
            'applied_amount' => 100,
            'advance_amount' => 0,
            'method' => 'nagad',
            'reference' => 'GLOBAL-FEE-REFERENCE',
            'collected_by' => $admin->id,
            'received_at' => now(),
        ]);
    }

    public function test_multiple_cash_payments_without_external_references_are_allowed(): void
    {
        [$admin, $branch, $student, $course, $batch, $admission, $invoice] = $this->setupInvoice();

        foreach ([100, 150] as $amount) {
            $this->actingAs($admin)->post(route('admin.coaching-fees.payments.store', $invoice), [
                'amount' => $amount,
                'method' => 'cash',
                'reference' => '',
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(2, StudentFeePayment::where('invoice_id', $invoice->id)->count());
        $this->assertSame(250.0, $invoice->fresh()->paidAmount());
        $this->assertSame(750.0, $invoice->fresh()->dueAmount());
    }

    public function test_overpayment_is_kept_as_advance_credit_and_applied_to_the_next_invoice_once(): void
    {
        [$admin, $branch, $student, $course, $batch, $admission, $invoice] = $this->setupInvoice(1000);

        $this->actingAs($admin)->post(route('admin.coaching-fees.payments.store', $invoice), [
            'amount' => 1200,
            'method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $payment = StudentFeePayment::where('invoice_id', $invoice->id)->firstOrFail();
        $credit = StudentFeeCredit::where('payment_id', $payment->id)->firstOrFail();

        $this->assertSame(1200.0, (float) $payment->amount);
        $this->assertSame(1000.0, (float) $payment->applied_amount);
        $this->assertSame(200.0, (float) $payment->advance_amount);
        $this->assertSame(200.0, (float) $credit->amount);
        $this->assertSame(0.0, $invoice->fresh()->dueAmount());

        $this->post(route('admin.coaching-fees.invoices.store'), [
            'coaching_admission_id' => $admission->id,
            'description' => 'Next month coaching fee',
            'amount' => 500,
            'discount' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $nextInvoice = StudentFeeInvoice::where('description', 'Next month coaching fee')->firstOrFail();

        $this->assertSame(200.0, (float) $nextInvoice->creditApplications()->sum('amount'));
        $this->assertSame(300.0, $nextInvoice->fresh()->dueAmount());
        $this->assertSame('partial', $nextInvoice->fresh()->status);
        $this->assertSame(0.0, $credit->fresh()->remainingAmount());
        $this->assertDatabaseCount('student_fee_credit_applications', 1);
    }
}
