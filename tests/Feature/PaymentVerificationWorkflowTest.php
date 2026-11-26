<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Ebook;
use App\Models\EbookPurchase;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

}
