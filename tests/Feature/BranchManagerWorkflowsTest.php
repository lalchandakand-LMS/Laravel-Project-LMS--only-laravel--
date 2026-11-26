<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\CoachingBatch;
use App\Models\Course;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StudentFeeInvoice;
use App\Models\StudentFeePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagerWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(string $code = 'DHK'): array
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        $branch = Branch::create([
            'name' => $code.' Branch',
            'code' => $code,
            'manager_id' => $manager->id,
            'is_active' => true,
            'payment_methods' => ['bkash', 'cash', 'office', 'bank', 'nagad'],
        ]);
        $manager->update(['branch_id' => $branch->id]);

        return [$manager, $branch];
    }

    private function order(User $student, Branch $branch, float $amount = 100): Order
    {
        return Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'subtotal' => $amount,
            'discount' => 0,
            'total' => $amount,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01700000000',
            'email' => $student->email,
        ]);
    }

    public function test_branch_manager_can_create_and_manage_only_own_branch_teachers(): void
    {
        [$manager, $branch] = $this->manager();
        [, $otherBranch] = $this->manager('CTG');
        $otherTeacher = User::factory()->create(['role' => 'instructor', 'branch_id' => $otherBranch->id]);

        $this->actingAs($manager)->post(route('branch.staff.store'), [
            'name' => 'Branch English Teacher',
            'email' => 'teacher.dhk@example.test',
            'phone' => '01700000000',
            'designation' => 'English Teacher',
            'password' => 'StrongPass123!',
            'is_active' => '1',
        ])->assertRedirect(route('branch.staff'));

        $teacher = User::where('email', 'teacher.dhk@example.test')->firstOrFail();
        $this->assertSame('instructor', $teacher->role);
        $this->assertSame($branch->id, $teacher->branch_id);

        $this->actingAs($manager)->get(route('branch.staff.edit', $otherTeacher))
            ->assertNotFound();

        $this->actingAs($manager)->put(route('branch.staff.update', $teacher), [
            'name' => 'Updated Teacher',
            'email' => $teacher->email,
            'phone' => '01711111111',
            'designation' => 'Senior English Teacher',
            'password' => '',
            'is_active' => '1',
        ])->assertRedirect(route('branch.staff'));

        $this->assertSame($branch->id, $teacher->fresh()->branch_id);
        $this->assertSame('Updated Teacher', $teacher->fresh()->name);
    }

    public function test_branch_manager_can_approve_only_online_batch_applications_for_own_branch(): void
    {
        [$manager, $branch] = $this->manager();
        [, $otherBranch] = $this->manager('CTG');
        $course = Course::create(['title' => 'Branch Online Course', 'price' => 1000, 'is_published' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $branch->id]);
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $otherBranch->id]);
        $batch = Batch::create(['course_id' => $course->id, 'branch_id' => $branch->id, 'name' => 'DHK Online', 'code' => 'DHK-ONLINE', 'status' => 'ongoing']);
        $otherBatch = Batch::create(['course_id' => $course->id, 'branch_id' => $otherBranch->id, 'name' => 'CTG Online', 'code' => 'CTG-ONLINE', 'status' => 'ongoing']);
        $application = \App\Models\OnlineBatchApplication::create(['student_id' => $student->id, 'batch_id' => $batch->id, 'course_id' => $course->id, 'status' => 'pending']);
        $otherApplication = \App\Models\OnlineBatchApplication::create(['student_id' => $otherStudent->id, 'batch_id' => $otherBatch->id, 'course_id' => $course->id, 'status' => 'pending']);

        $this->actingAs($manager)->post(route('branch.admissions.online.approve', $application), [])
            ->assertRedirect();

        $this->assertDatabaseHas('online_batch_enrollments', ['application_id' => $application->id, 'status' => 'active']);

        $this->actingAs($manager)->post(route('branch.admissions.online.approve', $otherApplication), [])
            ->assertForbidden();
        $this->assertDatabaseHas('online_batch_applications', ['id' => $otherApplication->id, 'status' => 'pending']);
    }

    public function test_branch_manager_can_approve_own_coaching_admission_and_generate_fee_invoice(): void
    {
        [$manager, $branch] = $this->manager();
        $course = Course::create(['title' => 'Physical English', 'price' => 0, 'is_published' => true]);
        $batch = CoachingBatch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => 'DHK Physical',
            'code' => 'DHK-PHYS',
            'admission_fee' => 500,
            'monthly_fee' => 1500,
            'status' => 'ongoing',
        ]);
        $admission = CoachingAdmission::create([
            'student_name' => 'New Branch Student',
            'student_email' => 'new.student@example.test',
            'student_phone' => '01722222222',
            'coaching_batch_id' => $batch->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->post(route('branch.admissions.coaching.approve', $admission), [
            'admin_note' => 'Documents checked',
        ])->assertRedirect();

        $admission->refresh();
        $this->assertSame('awaiting_payment', $admission->status);
        $this->assertDatabaseHas('student_fee_invoices', [
            'coaching_admission_id' => $admission->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'admission',
            'status' => 'unpaid',
            'created_by' => $manager->id,
        ]);
        $this->assertSame($branch->id, $admission->student->branch_id);
    }

    public function test_branch_manager_can_review_own_attendance_but_cannot_open_another_branch_session(): void
    {
        [$manager, $branch] = $this->manager();
        [, $otherBranch] = $this->manager('CTG');
        $teacher = User::factory()->create(['role' => 'instructor', 'branch_id' => $branch->id]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $otherTeacher = User::factory()->create(['role' => 'instructor', 'branch_id' => $otherBranch->id]);
        $course = Course::create(['title' => 'Attendance English', 'price' => 0, 'is_published' => true]);
        $batch = Batch::create(['course_id' => $course->id, 'branch_id' => $branch->id, 'name' => 'DHK Attendance', 'code' => 'DHK-ATT', 'status' => 'ongoing']);
        $otherBatch = Batch::create(['course_id' => $course->id, 'branch_id' => $otherBranch->id, 'name' => 'CTG Attendance', 'code' => 'CTG-ATT', 'status' => 'ongoing']);
        $session = AttendanceSession::create([
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'instructor_id' => $teacher->id,
            'mode' => 'offline',
            'starts_at' => now(),
            'status' => 'submitted',
        ]);
        Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'marked_by' => $teacher->id,
        ]);
        $otherSession = AttendanceSession::create([
            'batch_id' => $otherBatch->id,
            'course_id' => $course->id,
            'branch_id' => $otherBranch->id,
            'instructor_id' => $otherTeacher->id,
            'mode' => 'offline',
            'starts_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($manager)->get(route('branch.attendance.show', $otherSession))->assertNotFound();
        $this->actingAs($manager)->post(route('branch.attendance.review', $session), [
            'action' => 'approve',
            'review_note' => 'Checked',
        ])->assertRedirect();

        $this->assertSame('approved', $session->fresh()->status);
    }

    public function test_branch_manager_can_verify_own_order_payment_and_cannot_verify_other_branch_payment(): void
    {
        [$manager, $branch] = $this->manager();
        [, $otherBranch] = $this->manager('CTG');
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $otherStudent = User::factory()->create(['role' => 'student', 'branch_id' => $otherBranch->id]);
        $order = $this->order($student, $branch);
        $otherOrder = $this->order($otherStudent, $otherBranch);
        $payment = Payment::create(['order_id' => $order->id, 'gateway' => 'bkash', 'transaction_id' => 'DHK-TRX', 'amount' => 100, 'status' => 'pending']);
        $otherPayment = Payment::create(['order_id' => $otherOrder->id, 'gateway' => 'bkash', 'transaction_id' => 'CTG-TRX', 'amount' => 100, 'status' => 'pending']);

        $this->actingAs($manager)->post(route('branch.finance.payments.review', $payment), [
            'action' => 'verify',
        ])->assertRedirect();
        $this->assertSame('verified', $payment->fresh()->status);

        $this->actingAs($manager)->post(route('branch.finance.payments.review', $otherPayment), [
            'action' => 'verify',
        ])->assertNotFound();
        $this->assertSame('pending', $otherPayment->fresh()->status);
    }

    public function test_branch_manager_can_record_offline_and_coaching_fee_collections_for_own_branch(): void
    {
        [$manager, $branch] = $this->manager();
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $order = $this->order($student, $branch, 200);

        $this->actingAs($manager)->post(route('branch.finance.orders.offline-payment', $order), [
            'gateway' => 'cash',
            'amount' => 100,
            'reference' => 'CASH-DHK-001',
        ])->assertRedirect();
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'gateway' => 'cash', 'status' => 'verified']);

        $course = Course::create(['title' => 'Coaching Fee English', 'price' => 0, 'is_published' => true]);
        $batch = CoachingBatch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => 'Fee Batch',
            'code' => 'DHK-FEE',
            'admission_fee' => 0,
            'monthly_fee' => 1500,
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
            'description' => 'Monthly fee',
            'issued_at' => now()->toDateString(),
            'amount' => 1500,
            'discount' => 0,
            'status' => 'unpaid',
        ]);

        $this->actingAs($manager)->post(route('branch.finance.invoices.collect', $invoice), [
            'amount' => 500,
            'method' => 'cash',
            'reference' => 'FEE-DHK-001',
        ])->assertRedirect();

        $this->assertDatabaseHas('student_fee_payments', [
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'applied_amount' => 500,
            'method' => 'cash',
        ]);
    }
}
