<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingAdmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_online_coaching_admission_application(): void
    {
        $branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $course = Course::create(['title' => 'Spoken English', 'price' => 3000, 'is_published' => true]);
        $batch = Batch::create(['name' => 'Morning', 'code' => 'MORN1', 'branch_id' => $branch->id, 'course_id' => $course->id, 'status' => 'upcoming']);

        $response = $this->post(route('coaching-admission.store'), [
            'student_name' => 'Test Student', 'student_email' => 'student@example.com',
            'student_phone' => '01700000000', 'guardian_name' => 'Parent',
            'guardian_phone' => '01800000000', 'batch_id' => $batch->id,
        ]);

        $response->assertRedirect(route('coaching-admission.create'));
        $this->assertDatabaseHas('coaching_admissions', ['student_email' => 'student@example.com', 'status' => 'pending', 'branch_id' => $branch->id]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_approval_creates_student_account_and_batch_enrollment_without_online_order(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $course = Course::create(['title' => 'Spoken English', 'price' => 3000, 'is_published' => true]);
        $batch = Batch::create(['name' => 'Morning', 'code' => 'MORN1', 'branch_id' => $branch->id, 'course_id' => $course->id, 'status' => 'upcoming']);
        $application = CoachingAdmission::create([
            'student_name' => 'Test Student', 'student_email' => 'student@example.com', 'student_phone' => '01700000000',
            'branch_id' => $branch->id, 'course_id' => $course->id, 'batch_id' => $batch->id,
        ]);
        $this->actingAs($admin)->post(route('admin.coaching-admissions.approve', $application), ['admin_note' => 'Approved'])->assertRedirect();
        $student = User::where('email', 'student@example.com')->firstOrFail();
        $this->assertDatabaseHas('coaching_admissions', ['id' => $application->id, 'student_id' => $student->id, 'status' => 'approved']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        $this->assertDatabaseCount('orders', 0);
    }
    public function test_coaching_fee_payment_tracks_partial_and_advance_credit_separately_from_online_orders(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $course = Course::create(['title' => 'Spoken English', 'price' => 3000, 'is_published' => true]);
        $batch = Batch::create(['name' => 'Morning', 'code' => 'MORN1', 'branch_id' => $branch->id, 'course_id' => $course->id, 'status' => 'ongoing']);
        $admission = CoachingAdmission::create([
            'student_name' => $student->name, 'student_email' => $student->email, 'student_phone' => '01700000000',
            'branch_id' => $branch->id, 'course_id' => $course->id, 'batch_id' => $batch->id,
            'student_id' => $student->id, 'status' => 'approved', 'admitted_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.coaching-fees.invoices.store'), [
            'coaching_admission_id' => $admission->id, 'description' => 'First month', 'amount' => 100, 'discount' => 0,
        ])->assertRedirect();
        $first = \App\Models\StudentFeeInvoice::firstOrFail();

        $this->post(route('admin.coaching-fees.payments.store', $first), [
            'amount' => 150, 'method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('student_fee_payments', ['invoice_id' => $first->id, 'amount' => 150, 'applied_amount' => 100, 'advance_amount' => 50]);
        $this->assertDatabaseHas('student_fee_credits', ['student_id' => $student->id, 'amount' => 50]);

        $this->post(route('admin.coaching-fees.invoices.store'), [
            'coaching_admission_id' => $admission->id, 'description' => 'Second month', 'amount' => 40, 'discount' => 0,
        ])->assertRedirect();

        $second = \App\Models\StudentFeeInvoice::where('description', 'Second month')->firstOrFail();
        $this->assertSame(0.0, $second->dueAmount());
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertEquals(10.00, (float) \App\Models\StudentFeeCredit::firstOrFail()->remainingAmount());
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guardian_can_only_view_linked_child_coaching_fee_report(): void
    {
        $guardian = User::create(['name' => 'Guardian', 'email' => 'guardian@example.com', 'password' => 'password', 'role' => 'guardian', 'is_active' => true]);
        $child = User::create(['name' => 'Child', 'email' => 'child@example.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        $guardian->children()->attach($child->id, ['relation' => 'father']);

        $this->actingAs($guardian)->get(route('guardian.child.coaching-fees', $child))->assertOk();
        $this->get(route('guardian.child.coaching-fees', $other))->assertForbidden();
    }

}