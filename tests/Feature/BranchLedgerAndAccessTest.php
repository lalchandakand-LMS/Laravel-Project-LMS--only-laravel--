<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\CoachingBatch;
use App\Models\CoachingEnrollment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\OnlineBatchApplication;
use App\Models\OnlineBatchEnrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\StudentFeeInvoice;
use App\Models\StudentFeePayment;
use App\Models\StudentFeeCredit;
use App\Models\StudentFeeCreditApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BranchLedgerAndAccessTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $code, ?User $manager = null): Branch
    {
        return Branch::create([
            'name' => $code.' Branch',
            'code' => $code,
            'manager_id' => $manager?->id,
            'is_active' => true,
            'payment_methods' => ['bkash', 'nagad'],
        ]);
    }

    private function order(User $student, Branch $branch, float $amount = 1000): Order
    {
        return Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => $amount,
            'discount' => 0,
            'total' => $amount,
            'status' => 'paid',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);
    }

    public function test_branch_revenue_uses_order_branch_and_verified_ledger_not_the_students_current_branch(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $firstBranch = $this->branch('DHK');
        $secondBranch = $this->branch('CTG');
        $student->update(['branch_id' => $firstBranch->id]);

        $firstOrder = $this->order($student, $firstBranch, 1000);
        $secondOrder = $this->order($student, $secondBranch, 500);

        Payment::create(['order_id' => $firstOrder->id, 'gateway' => 'bkash', 'amount' => 1000, 'status' => 'verified']);
        Payment::create(['order_id' => $secondOrder->id, 'gateway' => 'nagad', 'amount' => 500, 'status' => 'verified']);
        PaymentAdjustment::create(['order_id' => $firstOrder->id, 'type' => 'refund', 'amount' => 100, 'reason' => 'Partial refund']);
        PaymentAdjustment::create(['order_id' => $firstOrder->id, 'type' => 'credit', 'amount' => 50, 'reason' => 'Approved credit']);

        $student->update(['branch_id' => $secondBranch->id]);

        $this->assertSame(900.0, $firstBranch->fresh()->revenue());
        $this->assertSame(500.0, $secondBranch->fresh()->revenue());
    }

    public function test_branch_manager_dashboard_counts_all_three_branch_enrollment_types(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $branch = $this->branch('DHK', $manager);
        $manager->update(['branch_id' => $branch->id]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $branch->id]);
        $course = Course::create(['title' => 'Branch English', 'price' => 1200, 'is_published' => true]);

        $batch = Batch::create([
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'name' => 'Online Evening',
            'code' => 'DHK-ONL',
            'status' => 'ongoing',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'price_paid' => 1200,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $application = OnlineBatchApplication::create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);
        OnlineBatchEnrollment::create([
            'application_id' => $application->id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'status' => 'active',
            'admitted_at' => now(),
        ]);

        $coachingBatch = CoachingBatch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => 'Physical Morning',
            'code' => 'DHK-PHY',
            'admission_fee' => 500,
            'monthly_fee' => 1500,
            'status' => 'ongoing',
        ]);
        $admission = CoachingAdmission::create([
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '01700000000',
            'coaching_batch_id' => $coachingBatch->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => 'admitted',
            'admitted_at' => now(),
        ]);
        CoachingEnrollment::create([
            'coaching_admission_id' => $admission->id,
            'student_id' => $student->id,
            'coaching_batch_id' => $coachingBatch->id,
            'course_id' => $course->id,
            'status' => 'active',
            'admitted_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('branch.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['enrollments'] === 3);
    }

    public function test_branch_manager_cannot_view_students_or_revenue_from_another_branch(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $ownBranch = $this->branch('DHK', $manager);
        $manager->update(['branch_id' => $ownBranch->id]);
        $otherBranch = $this->branch('CTG');
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $otherBranch->id]);

        $ownOrder = $this->order($ownStudent, $ownBranch, 1000);
        $otherOrder = $this->order($otherStudent, $otherBranch, 2500);

        $this->actingAs($manager)->get(route('branch.students'))
            ->assertOk()
            ->assertSee($ownStudent->name)
            ->assertDontSee($otherStudent->name);

        $this->actingAs($manager)->get(route('branch.students.show', $otherStudent))
            ->assertForbidden();

        $this->actingAs($manager)->get(route('branch.revenue'))
            ->assertOk()
            ->assertSee($ownOrder->order_no)
            ->assertDontSee($otherOrder->order_no);
    }

    public function test_admin_branch_assignment_syncs_manager_branch_and_rejects_non_managers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.branches.store'), [
            'name' => 'Dhaka Centre',
            'code' => 'DHK-CENTER',
            'manager_id' => $manager->id,
            'is_active' => '1',
            'payment_methods' => ['bkash'],
        ])->assertRedirect(route('admin.branches.index'));

        $branch = Branch::where('code', 'DHK-CENTER')->firstOrFail();
        $this->assertSame($branch->id, $manager->fresh()->branch_id);

        $this->actingAs($admin)->from(route('admin.branches.create'))->post(route('admin.branches.store'), [
            'name' => 'Invalid Manager Centre',
            'code' => 'BAD-MANAGER',
            'manager_id' => $teacher->id,
            'is_active' => '1',
        ])->assertSessionHasErrors('manager_id');

        $this->assertDatabaseMissing('branches', ['code' => 'BAD-MANAGER']);
    }

    public function test_branch_manager_cannot_collect_coaching_fees_with_a_disabled_branch_payment_method(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $branch = $this->branch('FEE-METHOD', $manager);
        $manager->update(['branch_id' => $branch->id]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $branch->id]);
        $course = Course::create(['title' => 'Fee Method Course', 'price' => 1000, 'is_published' => true]);
        $coachingBatch = CoachingBatch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => 'Fee Method Batch',
            'code' => 'FEE-METHOD-BATCH',
            'admission_fee' => 500,
            'monthly_fee' => 1500,
            'status' => 'ongoing',
        ]);
        $admission = CoachingAdmission::create([
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '01700000000',
            'coaching_batch_id' => $coachingBatch->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => 'admitted',
            'admitted_at' => now(),
        ]);
        $invoice = StudentFeeInvoice::create([
            'student_id' => $student->id,
            'coaching_admission_id' => $admission->id,
            'coaching_batch_id' => $coachingBatch->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'recurring',
            'description' => 'Monthly tuition',
            'issued_at' => now()->toDateString(),
            'amount' => 1500,
            'discount' => 0,
            'status' => 'unpaid',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('branch.finance.invoices.collect', $invoice), [
            'amount' => 500,
            'method' => 'rocket',
            'reference' => 'DISABLED-BRANCH-METHOD',
        ])->assertRedirect()->assertSessionHasErrors('method');

        $this->assertDatabaseCount('student_fee_payments', 0);
    }


    public function test_branch_coaching_collected_counts_cash_once_and_does_not_count_unused_advance_as_missing(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $branch = $this->branch('FEE-CASH-AGG', $manager);
        $manager->update(['branch_id' => $branch->id]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $branch->id]);
        $course = Course::create(['title' => 'Advance Credit Course', 'price' => 1200, 'is_published' => true]);
        $batch = CoachingBatch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => 'Advance Credit Batch',
            'code' => 'FEE-CASH-AGG-B',
            'status' => 'ongoing',
        ]);
        $admission = CoachingAdmission::create([
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '01700000000',
            'coaching_batch_id' => $batch->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => 'admitted',
            'admitted_at' => now(),
        ]);
        $invoice = StudentFeeInvoice::create([
            'student_id' => $student->id,
            'coaching_admission_id' => $admission->id,
            'coaching_batch_id' => $batch->id,
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'recurring',
            'description' => 'First month fee',
            'issued_at' => now()->toDateString(),
            'amount' => 1000,
            'discount' => 0,
            'status' => 'paid',
            'created_by' => $manager->id,
        ]);
        $payment = StudentFeePayment::create([
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'amount' => 1200,
            'applied_amount' => 1000,
            'advance_amount' => 200,
            'method' => 'cash',
            'collected_by' => $manager->id,
            'received_at' => now(),
        ]);
        $credit = StudentFeeCredit::create([
            'student_id' => $student->id,
            'payment_id' => $payment->id,
            'amount' => 200,
            'note' => 'Advance payment balance',
        ]);

        $nextInvoice = StudentFeeInvoice::create([
            'student_id' => $student->id,
            'coaching_admission_id' => $admission->id,
            'coaching_batch_id' => $batch->id,
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'recurring',
            'description' => 'Second month fee',
            'issued_at' => now()->toDateString(),
            'amount' => 500,
            'discount' => 0,
            'status' => 'partial',
            'created_by' => $manager->id,
        ]);
        StudentFeeCreditApplication::create([
            'credit_id' => $credit->id,
            'invoice_id' => $nextInvoice->id,
            'amount' => 100,
            'applied_by' => $manager->id,
        ]);

        $this->actingAs($manager)->get(route('branch.finance'))
            ->assertOk()
            ->assertViewHas('totals', function (array $totals) {
                return $totals['coaching_collected'] === 1200.0
                    && $totals['coaching_due'] === 400.0;
            });
    }

    public function test_branch_finance_dashboard_uses_aggregate_totals_and_renders_for_an_empty_branch(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $branch = $this->branch('FIN-EMPTY', $manager);
        $manager->update(['branch_id' => $branch->id]);

        $this->actingAs($manager)->get(route('branch.finance'))
            ->assertOk()
            ->assertViewHas('totals', function (array $totals) {
                return $totals['collected'] === 0.0
                    && $totals['order_due'] === 0.0
                    && $totals['coaching_collected'] === 0.0
                    && $totals['coaching_due'] === 0.0
                    && $totals['pending_verification'] === 0;
            });
    }


    public function test_branch_manager_with_inconsistent_branch_assignment_is_denied_across_finance_admissions_and_attendance(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $profileBranch = $this->branch('SCOPE-PROFILE');
        $managedBranch = $this->branch('SCOPE-MANAGED', $manager);
        $manager->update(['branch_id' => $profileBranch->id]);

        $this->actingAs($manager)->get(route('branch.finance'))->assertForbidden();
        $this->actingAs($manager)->get(route('branch.admissions.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('branch.attendance'))->assertForbidden();

        $this->assertNotSame($profileBranch->id, $managedBranch->id);
    }

    public function test_branch_dashboard_pending_balance_uses_ledger_adjustments_and_branch_scope(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $branch = $this->branch('PENDING-DHK', $manager);
        $manager->update(['branch_id' => $branch->id]);
        $otherBranch = $this->branch('PENDING-CTG');
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $branch->id]);
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $otherBranch->id]);

        $adjustedOrder = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'partially_paid',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);
        Payment::create([
            'order_id' => $adjustedOrder->id,
            'gateway' => 'bkash',
            'amount' => 400,
            'status' => 'verified',
        ]);
        PaymentAdjustment::create([
            'order_id' => $adjustedOrder->id,
            'type' => 'refund',
            'amount' => 50,
            'reason' => 'Partial refund',
        ]);
        PaymentAdjustment::create([
            'order_id' => $adjustedOrder->id,
            'type' => 'credit',
            'amount' => 25,
            'reason' => 'Approved credit',
        ]);

        Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 200,
            'discount' => 0,
            'total' => 200,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);
        Order::create([
            'user_id' => $otherStudent->id,
            'branch_id' => $otherBranch->id,
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'status' => 'pending',
            'name' => $otherStudent->name,
            'phone' => '01700000000',
            'email' => $otherStudent->email,
        ]);

        $this->actingAs($manager)->get(route('branch.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['students'] === 1
                && $stats['pending'] === 825.0);
    }

    public function test_branch_finance_revenue_excludes_internal_credit_transfers(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = $this->branch('CASH-NET');
        $order = $this->order($student, $branch, 1000);
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bkash',
            'amount' => 1000,
            'status' => 'verified',
        ]);
        PaymentAdjustment::create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => 100,
            'reason' => 'Refund reduces cash collected.',
        ]);
        PaymentAdjustment::create([
            'order_id' => $order->id,
            'type' => 'credit',
            'amount' => 200,
            'reason' => 'Internal transfer; not new cash received.',
        ]);

        $this->assertSame(900.0, $branch->fresh()->revenue());
    }

    public function test_branch_manager_can_view_only_payment_proofs_for_their_own_branch(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payment-proofs/own-branch.pdf', 'own branch proof');
        Storage::disk('local')->put('payment-proofs/other-branch.pdf', 'other branch proof');

        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $ownBranch = $this->branch('PROOF-OWN', $manager);
        $manager->update(['branch_id' => $ownBranch->id]);
        $otherBranch = $this->branch('PROOF-OTHER');
        $ownStudent = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $ownBranch->id]);
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $otherBranch->id]);

        $ownPayment = Payment::create([
            'order_id' => $this->order($ownStudent, $ownBranch)->id,
            'gateway' => 'bank',
            'transaction_id' => 'OWN-BRANCH-PROOF',
            'amount' => 500,
            'status' => 'pending',
            'proof_path' => 'payment-proofs/own-branch.pdf',
        ]);
        $otherPayment = Payment::create([
            'order_id' => $this->order($otherStudent, $otherBranch)->id,
            'gateway' => 'bank',
            'transaction_id' => 'OTHER-BRANCH-PROOF',
            'amount' => 500,
            'status' => 'pending',
            'proof_path' => 'payment-proofs/other-branch.pdf',
        ]);

        $this->actingAs($manager)
            ->get(route('branch.finance.payments.proof', $ownPayment))
            ->assertOk()
            ->assertDownload('own-branch.pdf');

        $this->actingAs($manager)
            ->get(route('branch.finance.payments.proof', $otherPayment))
            ->assertNotFound();
    }

    public function test_branch_manager_cannot_review_or_record_offline_payment_for_another_branch(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $ownBranch = $this->branch('PAY-SCOPE-OWN', $manager);
        $manager->update(['branch_id' => $ownBranch->id]);
        $otherBranch = $this->branch('PAY-SCOPE-OTHER');
        $ownStudent = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $ownBranch->id]);
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $otherBranch->id]);
        $otherOrder = $this->order($otherStudent, $otherBranch, 1000);
        $otherOrder->update(['status' => 'pending']);
        $otherPayment = Payment::create([
            'order_id' => $otherOrder->id,
            'gateway' => 'bank',
            'transaction_id' => 'CROSS-BRANCH-REVIEW',
            'amount' => 250,
            'status' => 'pending',
        ]);

        $this->actingAs($manager)
            ->post(route('branch.finance.payments.review', $otherPayment), [
                'action' => 'verify',
            ])
            ->assertNotFound();

        $this->actingAs($manager)
            ->post(route('branch.finance.orders.offline-payment', $otherOrder), [
                'gateway' => 'cash',
                'amount' => 100,
                'reference' => 'CROSS-BRANCH-OFFLINE',
            ])
            ->assertNotFound();

        $this->assertSame('pending', $otherPayment->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

}
