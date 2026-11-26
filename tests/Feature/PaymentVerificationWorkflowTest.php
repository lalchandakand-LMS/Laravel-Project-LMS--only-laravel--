<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Ebook;
use App\Models\EbookPurchase;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentVerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function orderForStudent(float $total = 1000): array
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Payment Workflow Branch',
            'code' => 'PAY-WF-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'payment_methods' => ['nagad', 'bank', 'cash', 'office'],
            'nagad_number' => '01712345678',
            'bank_name' => 'Test Bank',
            'bank_branch' => 'Dhaka',
            'bank_account_name' => 'Alom English Center',
            'bank_account_number' => '1234567890',
        ]);
        $student->update(['branch_id' => $branch->id]);
        $order = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);

        return [$student, $admin, $branch, $order];
    }

    public function test_partial_payments_are_verified_separately_and_due_balance_updates(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();

        $this->actingAs($student)->post(route('checkout.pay', $order->order_no), [
            'gateway' => 'nagad',
            'amount' => 400,
            'sender_number' => '01711111111',
            'transaction_id' => 'PART-400',
        ])->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), [
            'action' => 'verify',
        ])->assertRedirect();

        $this->assertSame('partially_paid', $order->fresh()->status);
        $this->assertSame(400.0, $order->fresh()->amountPaid());
        $this->assertSame(600.0, $order->fresh()->dueAmount());

        $this->actingAs($student)->post(route('checkout.pay', $order->order_no), [
            'gateway' => 'nagad',
            'amount' => 600,
            'sender_number' => '01711111111',
            'transaction_id' => 'PART-600',
        ])->assertRedirect();

        $secondPayment = Payment::where('transaction_id', 'PART-600')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.payments.verify', $secondPayment), [
            'action' => 'verify',
        ])->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(0.0, $order->fresh()->dueAmount());
        $this->assertSame(2, $order->payments()->where('status', 'verified')->count());
    }

    public function test_payment_verification_rolls_back_when_purchased_course_is_archived(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $course = Course::create([
            'title' => 'Archived Before Payment Review',
            'slug' => 'archived-before-payment-review',
            'price' => 1000,
            'is_published' => true,
        ]);
        $order->items()->create([
            'item_type' => 'course',
            'item_id' => $course->id,
            'title' => $course->title,
            'price' => 1000,
            'qty' => 1,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 1000,
            'sender_number' => '01711111111',
            'transaction_id' => 'ARCHIVED-COURSE-PAYMENT',
            'status' => 'pending',
        ]);

        $course->delete();

        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), [
            'action' => 'verify',
        ])->assertRedirect()->assertSessionHasErrors('course');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_student_cannot_submit_second_payment_while_one_is_pending(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 500,
            'sender_number' => '01711111111',
            'transaction_id' => 'WAITING-TRX',
            'status' => 'pending',
        ]);

        $this->actingAs($student)->post(route('checkout.pay', $order->order_no), [
            'gateway' => 'nagad',
            'amount' => 500,
            'sender_number' => '01711111111',
            'transaction_id' => 'SECOND-TRX',
        ])->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_bank_transfer_requires_private_deposit_proof_upload(): void
    {
        Storage::fake('local');
        [$student, $admin, $branch, $order] = $this->orderForStudent();

        $this->actingAs($student)->post(route('checkout.pay', $order->order_no), [
            'gateway' => 'bank',
            'amount' => 1000,
            'sender_number' => 'Alom English Center',
            'transaction_id' => 'BANK-TRX-1',
        ])->assertSessionHasErrors('proof');

        $this->actingAs($student)->post(route('checkout.pay', $order->order_no), [
            'gateway' => 'bank',
            'amount' => 1000,
            'sender_number' => 'Alom English Center',
            'transaction_id' => 'BANK-TRX-2',
            'proof' => UploadedFile::fake()->create('deposit-slip.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $payment = Payment::where('transaction_id', 'BANK-TRX-2')->firstOrFail();
        $this->assertNotNull($payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);

        $response = $this->actingAs($admin)->get(route('admin.payments.proof', $payment))
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-disposition');

        $cacheControl = (string) $response->headers->get('cache-control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_failed_private_payment_proof_upload_does_not_create_a_pending_payment(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        $this->actingAs($student)->post(route('checkout.pay', $order->order_no), [
            'gateway' => 'bank',
            'amount' => 1000,
            'sender_number' => 'Alom English Center',
            'transaction_id' => 'FAILED-PROOF-STORAGE',
            'proof' => UploadedFile::fake()->create('deposit-slip.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseMissing('payments', ['transaction_id' => 'FAILED-PROOF-STORAGE']);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(1000.0, $order->fresh()->dueAmount());
    }

    public function test_payment_submission_rechecks_the_balance_under_the_order_lock(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'ALREADY-PAID-900',
            'amount' => 900,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $order->update(['status' => 'partially_paid']);

        $this->actingAs($student)->post(route('checkout.pay', $order->order_no), [
            'gateway' => 'nagad',
            'amount' => 200,
            'sender_number' => '01711111111',
            'transaction_id' => 'OVER-DUE-200',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('payments', ['transaction_id' => 'OVER-DUE-200']);
        $this->assertSame(100.0, $order->fresh()->dueAmount());
    }

    public function test_duplicate_transaction_reference_is_rejected_and_uploaded_proof_is_removed(): void
    {
        Storage::fake('local');
        [$firstStudent, $admin, $branch, $firstOrder] = $this->orderForStudent();
        [$secondStudent, $secondAdmin, $secondBranch, $secondOrder] = $this->orderForStudent();

        Payment::create([
            'order_id' => $firstOrder->id,
            'gateway' => 'nagad',
            'transaction_id' => 'GLOBAL-DUPLICATE-TRX',
            'amount' => 100,
            'status' => 'pending',
        ]);

        $this->actingAs($secondStudent)->post(route('checkout.pay', $secondOrder->order_no), [
            'gateway' => 'bank',
            'amount' => 500,
            'sender_number' => 'Alom English Center',
            'transaction_id' => 'GLOBAL-DUPLICATE-TRX',
            'proof' => UploadedFile::fake()->create('duplicate-slip.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('transaction_id');

        $this->assertSame(1, Payment::where('transaction_id', 'GLOBAL-DUPLICATE-TRX')->count());
        $this->assertSame([], Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_cash_and_office_collection_are_staff_recorded_as_verified_ledger_entries(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();

        $this->actingAs($student)->get(route('checkout.success', $order->order_no))
            ->assertOk()
            ->assertDontSee('Cash at office')
            ->assertDontSee('Office payment');

        $this->actingAs($admin)->post(route('admin.orders.offline-payment', $order->order_no), [
            'gateway' => 'cash',
            'amount' => 250,
            'reference' => 'CASH-RECEIPT-01',
            'note' => 'First installment collected at office.',
        ])->assertRedirect();

        $payment = Payment::where('transaction_id', 'CASH-RECEIPT-01')->firstOrFail();
        $this->assertSame('verified', $payment->status);
        $this->assertSame($admin->id, $payment->verified_by);
        $this->assertSame('partially_paid', $order->fresh()->status);
        $this->assertSame(750.0, $order->fresh()->dueAmount());
    }

    public function test_ebook_refund_revokes_purchase_and_repayment_restores_access(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $ebook = Ebook::create([
            'title' => 'Refundable eBook',
            'price' => 1000,
            'file' => 'ebook-files/refundable.pdf',
            'is_free' => false,
            'is_published' => true,
        ]);
        $order->items()->create([
            'item_type' => 'ebook',
            'item_id' => $ebook->id,
            'title' => $ebook->title,
            'price' => 1000,
            'qty' => 1,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'EBOOK-REFUND-TRX',
            'amount' => 1000,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $order->update(['status' => 'paid']);
        EbookPurchase::create([
            'user_id' => $student->id,
            'ebook_id' => $ebook->id,
            'order_id' => $order->id,
        ]);

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order->order_no), [
            'amount' => 1000,
            'reference' => 'EBOOK-REFUND-1000',
            'reason' => 'Full eBook refund.',
        ])->assertRedirect();

        $this->assertDatabaseMissing('ebook_purchases', [
            'user_id' => $student->id,
            'ebook_id' => $ebook->id,
        ]);
        $this->assertSame('refunded', $order->fresh()->status);

        $this->actingAs($admin)->post(route('admin.orders.status', $order->order_no), [
            'status' => 'pending',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.orders.offline-payment', $order->order_no), [
            'gateway' => 'cash',
            'amount' => 1000,
            'reference' => 'EBOOK-REPAY-1000',
        ])->assertRedirect();

        $this->assertDatabaseHas('ebook_purchases', [
            'user_id' => $student->id,
            'ebook_id' => $ebook->id,
            'order_id' => $order->id,
        ]);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_paid_order_cannot_be_downgraded_without_refund_ledger(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'cash',
            'transaction_id' => 'PAID-LEDGER',
            'amount' => 1000,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $order->update(['status' => 'paid']);

        $this->actingAs($admin)->post(route('admin.orders.status', $order->order_no), [
            'status' => 'pending',
        ])->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1000.0, $order->fresh()->amountPaid());
    }

    public function test_refund_reduces_net_paid_suspends_access_and_can_be_repaid(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $course = Course::create([
            'title' => 'Refundable English Course',
            'slug' => 'refundable-english-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $order->items()->create([
            'item_type' => 'course',
            'item_id' => $course->id,
            'title' => $course->title,
            'price' => 1000,
            'qty' => 1,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'order_id' => $order->id,
            'price_paid' => 1000,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 1000,
            'sender_number' => '01711111111',
            'transaction_id' => 'REFUND-ORIGINAL',
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $order->update(['status' => 'paid']);

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order->order_no), [
            'amount' => 300,
            'reference' => 'REFUND-300',
            'reason' => 'Student withdrew from the course.',
        ])->assertRedirect();

        $this->assertSame('partially_paid', $order->fresh()->status);
        $this->assertSame(700.0, $order->fresh()->amountPaid());
        $this->assertSame(300.0, $order->fresh()->dueAmount());
        $this->assertSame('suspended', $student->enrollments()->where('course_id', $course->id)->firstOrFail()->status);

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order->order_no), [
            'amount' => 700,
            'reference' => 'REFUND-700',
            'reason' => 'Remaining amount refunded.',
        ])->assertRedirect();

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(0.0, $order->fresh()->amountPaid());

        // Reopen the refunded order for a replacement settlement and restore the suspended enrollment.
        $this->actingAs($admin)->post(route('admin.orders.status', $order->order_no), [
            'status' => 'pending',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.orders.offline-payment', $order->order_no), [
            'gateway' => 'cash',
            'amount' => 1000,
            'reference' => 'REPAY-CASH-1000',
        ])->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('active', $student->enrollments()->where('course_id', $course->id)->firstOrFail()->status);
    }

    public function test_full_payment_verification_closes_other_pending_submissions_for_the_order(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 400,
            'sender_number' => '01711111111',
            'transaction_id' => 'SETTLEMENT-INITIAL-400',
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $order->update(['status' => 'partially_paid']);

        $settlingPayment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 600,
            'sender_number' => '01711111111',
            'transaction_id' => 'SETTLEMENT-FINAL-600',
            'status' => 'pending',
        ]);
        $stalePendingPayment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bank',
            'amount' => 600,
            'sender_number' => 'Alom English Center',
            'transaction_id' => 'SETTLEMENT-STALE-PENDING',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.payments.verify', $settlingPayment), [
            'action' => 'verify',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('verified', $settlingPayment->fresh()->status);
        $this->assertSame('rejected', $stalePendingPayment->fresh()->status);
        $this->assertSame($admin->id, $stalePendingPayment->fresh()->verified_by);
        $this->assertNotNull($stalePendingPayment->fresh()->verified_at);
        $this->assertStringContainsString('another payment completed the order', $stalePendingPayment->fresh()->rejection_reason);
    }

    public function test_payment_report_and_csv_export_are_admin_only(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 250,
            'sender_number' => '01711111111',
            'transaction_id' => 'REPORT-TRX',
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.payment-reports.index'))
            ->assertOk()->assertSee('Payment reports & ledger')->assertSee('REPORT-TRX');
        $this->actingAs($admin)->get(route('admin.payment-reports.export'))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($student)->get(route('admin.payment-reports.index'))->assertForbidden();
    }

    public function test_rejection_requires_a_reason_and_keeps_order_payable(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 1000,
            'sender_number' => '01711111111',
            'transaction_id' => 'REJECT-TRX',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), [
            'action' => 'reject',
        ])->assertSessionHasErrors('rejection_reason');

        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), [
            'action' => 'reject',
            'rejection_reason' => 'Transaction reference could not be found.',
        ])->assertRedirect();

        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(1000.0, $order->fresh()->dueAmount());
    }

    public function test_student_repayment_can_be_verified_after_full_refund(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 1000,
            'sender_number' => '01711111111',
            'transaction_id' => 'ORIGINAL-FULL-PAYMENT',
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $order->update(['status' => 'paid']);

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order->order_no), [
            'amount' => 1000,
            'reference' => 'FULL-REFUND-REFERENCE',
            'reason' => 'Full refund before replacement payment.',
        ])->assertRedirect();

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(0.0, $order->fresh()->amountPaid());

        $this->actingAs($admin)->post(route('admin.orders.status', $order->order_no), [
            'status' => 'pending',
        ])->assertRedirect();

        $this->actingAs($student)->post(route('checkout.pay', $order->order_no), [
            'gateway' => 'nagad',
            'amount' => 1000,
            'sender_number' => '01711111111',
            'transaction_id' => 'REPLACEMENT-PAYMENT',
        ])->assertRedirect();

        $replacement = Payment::where('transaction_id', 'REPLACEMENT-PAYMENT')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.payments.verify', $replacement), [
            'action' => 'verify',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1000.0, $order->fresh()->amountPaid());
    }

    public function test_pending_payment_cannot_be_verified_after_order_is_cancelled(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 1000,
            'sender_number' => '01711111111',
            'transaction_id' => 'CANCELLED-ORDER-PENDING-PAYMENT',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.orders.status', $order->order_no), [
            'status' => 'cancelled',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), [
            'action' => 'verify',
        ])->assertRedirect()->assertSessionHasErrors('payment');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame(0.0, $order->fresh()->amountPaid());
    }
    public function test_cancelled_order_closes_pending_payment_with_audit_reason(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 1000,
            'sender_number' => '01711111111',
            'transaction_id' => 'CANCEL-AND-CLOSE-PENDING',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.orders.status', $order->order_no), [
            'status' => 'cancelled',
        ])->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame($admin->id, $payment->fresh()->verified_by);
        $this->assertStringContainsString('cancelled', $payment->fresh()->rejection_reason);
    }

    public function test_student_cannot_submit_payment_above_remaining_balance_after_partial_settlement(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 400,
            'sender_number' => '01711111111',
            'transaction_id' => 'ALREADY-SETTLED-400',
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $order->update(['status' => 'partially_paid']);

        $this->actingAs($student)->post(route('checkout.pay', $order->order_no), [
            'gateway' => 'nagad',
            'amount' => 601,
            'sender_number' => '01711111111',
            'transaction_id' => 'OVER-DUE-601',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('payments', ['transaction_id' => 'OVER-DUE-601']);
        $this->assertSame(600.0, $order->fresh()->dueAmount());
    }

    public function test_cancelling_an_order_rejects_its_pending_payment(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 1000,
            'sender_number' => '01711111111',
            'transaction_id' => 'CANCEL-AND-CLOSE-PENDING',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.orders.status', $order->order_no), [
            'status' => 'cancelled',
        ])->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame($admin->id, $payment->fresh()->verified_by);
        $this->assertStringContainsString('cancelled', $payment->fresh()->rejection_reason);
    }

    public function test_admin_order_create_and_edit_use_the_effective_ebook_flash_sale_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Ebook Sale Order Branch',
            'code' => 'EBOOK-SALE-'.Str::upper(Str::random(5)),
            'is_active' => true,
            'payment_methods' => ['nagad', 'cash'],
        ]);
        $ebook = Ebook::create([
            'title' => 'Flash Sale eBook',
            'price' => 1000,
            'file' => 'ebook-files/flash-sale.pdf',
            'is_free' => false,
            'is_published' => true,
        ]);
        $sale = FlashSale::create([
            'name' => 'eBook Admin Order Sale',
            'type' => 'percent',
            'value' => 20,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $sale->ebooks()->attach($ebook->id);

        $payload = [
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'items' => [['type' => 'ebook', 'id' => $ebook->id]],
        ];

        $this->actingAs($admin)->post(route('admin.orders.store'), $payload)->assertRedirect();
        $createdOrder = Order::where('user_id', $student->id)->latest('id')->firstOrFail();
        $this->assertSame(800.0, (float) $createdOrder->subtotal);
        $this->assertSame(800.0, (float) $createdOrder->items()->firstOrFail()->price);

        $editableOrder = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
        ]);
        $editableOrder->items()->create([
            'item_type' => 'ebook',
            'item_id' => $ebook->id,
            'title' => $ebook->title,
            'price' => 1000,
            'qty' => 1,
        ]);

        $this->put(route('admin.orders.update', $editableOrder->order_no), [
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'items' => [['type' => 'ebook', 'id' => $ebook->id]],
        ])->assertRedirect();

        $this->assertSame(800.0, (float) $editableOrder->fresh()->total);
        $this->assertSame(800.0, (float) $editableOrder->fresh()->items()->firstOrFail()->price);
    }

    public function test_marking_an_order_paid_rejects_any_still_pending_payment_submission(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $pending = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'amount' => 500,
            'sender_number' => '01711111111',
            'transaction_id' => 'PENDING-BEFORE-ADMIN-PAID',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.orders.status', $order->order_no), [
            'status' => 'paid',
        ])->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('rejected', $pending->fresh()->status);
        $this->assertSame($admin->id, $pending->fresh()->verified_by);
        $this->assertStringContainsString('marked paid', $pending->fresh()->rejection_reason);
        $this->assertSame(1000.0, $order->fresh()->amountPaid());
    }


    public function test_rejecting_a_pending_payment_does_not_reopen_terminal_orders(): void
    {
        [$student, $admin, $branch, $cancelledOrder] = $this->orderForStudent();
        $cancelledOrder->update(['status' => 'cancelled']);
        $failedOrder = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'failed',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);
        $refundedOrder = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'refunded',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);

        foreach ([
            [$cancelledOrder, 'cancelled'],
            [$failedOrder, 'failed'],
            [$refundedOrder, 'refunded'],
        ] as [$order, $terminalStatus]) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway' => 'nagad',
                'amount' => 1000,
                'sender_number' => '01711111111',
                'transaction_id' => 'REJECT-TERMINAL-'.$terminalStatus.'-'.Str::upper(Str::random(5)),
                'status' => 'pending',
            ]);

            $this->actingAs($admin)->post(route('admin.payments.verify', $payment), [
                'action' => 'reject',
                'rejection_reason' => 'Payment proof could not be verified.',
            ])->assertRedirect();

            $this->assertSame('rejected', $payment->fresh()->status);
            $this->assertSame($terminalStatus, $order->fresh()->status);
        }
    }


    public function test_refund_cannot_exceed_or_over_refund_the_selected_payment(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();
        $first = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'REFUND-CAP-FIRST',
            'amount' => 400,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'REFUND-CAP-SECOND',
            'amount' => 600,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $order->update(['status' => 'paid']);

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order->order_no), [
            'payment_id' => $first->id,
            'amount' => 401,
            'reference' => 'REFUND-CAP-401',
            'reason' => 'Attempt exceeds selected payment.',
        ])->assertRedirect()->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payment_adjustments', 0);

        $this->post(route('admin.orders.refunds.store', $order->order_no), [
            'payment_id' => $first->id,
            'amount' => 300,
            'reference' => 'REFUND-CAP-300',
            'reason' => 'Valid partial refund against selected payment.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('admin.orders.refunds.store', $order->order_no), [
            'payment_id' => $first->id,
            'amount' => 101,
            'reference' => 'REFUND-CAP-101',
            'reason' => 'Attempt exceeds remaining selected payment balance.',
        ])->assertRedirect()->assertSessionHasErrors('amount');

        $this->assertSame(300.0, (float) PaymentAdjustment::where('payment_id', $first->id)->sum('amount'));
        $this->assertSame(700.0, $order->fresh()->amountPaid());
    }

    public function test_refund_cannot_exceed_verified_cash_when_order_balance_also_contains_internal_credit(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'CASH-RECEIVED-500',
            'amount' => 500,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        PaymentAdjustment::create([
            'order_id' => $order->id,
            'created_by' => $admin->id,
            'type' => 'credit',
            'amount' => 500,
            'reference' => 'INTERNAL-CREDIT-500',
            'reason' => 'Transfer of an existing account credit to this order.',
        ]);

        $this->assertSame(1000.0, $order->fresh()->amountPaid());
        $this->assertSame(500.0, $order->fresh()->refundableAmount());

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order->order_no), [
            'amount' => 501,
            'reference' => 'REFUND-INCLUDING-NON-CASH-CREDIT',
            'reason' => 'Attempt to refund more cash than was collected.',
        ])->assertRedirect()->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payment_adjustments', 1);
        $this->assertSame(500.0, $order->fresh()->refundableAmount());

        $this->post(route('admin.orders.refunds.store', $order->order_no), [
            'amount' => 500,
            'reference' => 'REFUND-ACTUAL-CASH-ONLY',
            'reason' => 'Refund the actual verified cash receipt only.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0.0, $order->fresh()->refundableAmount());
        $this->assertSame(500.0, $order->fresh()->amountPaid());
    }

    public function test_refund_reference_has_a_database_level_unique_constraint(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();

        PaymentAdjustment::create([
            'order_id' => $order->id,
            'created_by' => $admin->id,
            'type' => 'refund',
            'amount' => 10,
            'reference' => 'GLOBAL-UNIQUE-REFUND-REFERENCE',
            'reason' => 'First refund reference.',
        ]);

        $this->expectException(QueryException::class);

        PaymentAdjustment::create([
            'order_id' => $order->id,
            'created_by' => $admin->id,
            'type' => 'refund',
            'amount' => 10,
            'reference' => 'GLOBAL-UNIQUE-REFUND-REFERENCE',
            'reason' => 'Duplicate refund reference must be rejected by the database.',
        ]);
    }



    public function test_full_refund_rejects_any_remaining_pending_payment_submissions_with_audit_details(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'FULL-REFUND-VERIFIED',
            'amount' => 1000,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $pending = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'FULL-REFUND-PENDING',
            'amount' => 1000,
            'status' => 'pending',
        ]);
        $order->update(['status' => 'paid']);

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order->order_no), [
            'amount' => 1000,
            'reference' => 'FULL-REFUND-CLOSE-PENDING',
            'reason' => 'Full refund; close any outstanding payment attempts.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame('rejected', $pending->fresh()->status);
        $this->assertSame($admin->id, $pending->fresh()->verified_by);
        $this->assertNotNull($pending->fresh()->verified_at);
        $this->assertStringContainsString('fully refunded', $pending->fresh()->rejection_reason);
    }



    public function test_refund_is_blocked_while_provider_payment_is_pending_reconciliation(): void
    {
        [$student, $admin, $branch, $order] = $this->orderForStudent();

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'nagad',
            'transaction_id' => 'PROVIDER-SETTLED-CASH',
            'amount' => 1000,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
        $providerPayment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bkash',
            'provider_payment_id' => 'BKASH-PENDING-REFUND-GUARD',
            'amount' => 1000,
            'status' => 'pending',
        ]);
        $order->update(['status' => 'paid']);

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order->order_no), [
            'amount' => 1000,
            'reference' => 'REFUND-BLOCKED-PENDING-PROVIDER',
            'reason' => 'Must wait for provider reconciliation.',
        ])->assertRedirect()->assertSessionHasErrors('order');

        $this->assertDatabaseCount('payment_adjustments', 0);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('pending', $providerPayment->fresh()->status);
        $this->assertNull($providerPayment->fresh()->verified_at);
    }

    public function test_offline_payment_cannot_be_recorded_for_paid_failed_cancelled_or_refunded_orders(): void
    {
        foreach (['paid', 'failed', 'cancelled', 'refunded'] as $status) {
            [$student, $admin, $branch, $order] = $this->orderForStudent();
            $order->update(['status' => $status]);

            $this->actingAs($admin)->post(route('admin.orders.offline-payment', $order->order_no), [
                'gateway' => 'cash',
                'amount' => 1000,
                'reference' => 'TERMINAL-OFFLINE-'.$status,
                'note' => 'This must not be recorded.',
            ])->assertRedirect()->assertSessionHasErrors('order');

            $this->assertSame(0, $order->payments()->count());
            $this->assertSame($status, $order->fresh()->status);
        }
    }


}
