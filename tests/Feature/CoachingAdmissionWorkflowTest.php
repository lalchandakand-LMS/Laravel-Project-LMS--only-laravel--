<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\CoachingBatch;
use App\Models\CoachingEnrollment;
use App\Models\Course;
use App\Models\StudentFeeInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoachingAdmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupCoaching(): array
    {
        $branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $course = Course::create(['title' => 'Spoken English', 'price' => 3000, 'is_published' => true]);
        $batch = CoachingBatch::create([
            'name' => 'Physical Morning', 'code' => 'PHY-MORN', 'branch_id' => $branch->id,
            'course_id' => $course->id, 'admission_fee' => 500, 'monthly_fee' => 1500, 'status' => 'upcoming',
        ]);

        return [$branch, $course, $batch];
    }

    public function test_guest_can_submit_online_application_for_a_distinct_physical_coaching_batch(): void
    {
        [$branch, $course, $batch] = $this->setupCoaching();

        $this->post(route('coaching-admission.store'), [
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '01700000000',
            'coaching_batch_id' => $batch->id,
        ])->assertRedirect(route('coaching-admission.create'));

        $this->assertDatabaseHas('coaching_admissions', [
            'student_email' => 'student@example.com',
            'coaching_batch_id' => $batch->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_failed_admission_document_storage_rolls_back_the_application(): void
    {
        [, , $batch] = $this->setupCoaching();
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        $this->from(route('coaching-admission.create'))->post(route('coaching-admission.store'), [
            'student_name' => 'Storage Failure Student',
            'student_email' => 'storage-failure@example.com',
            'student_phone' => '01700000000',
            'coaching_batch_id' => $batch->id,
            'student_photo' => UploadedFile::fake()->image('student.png'),
        ])->assertRedirect(route('coaching-admission.create'))
            ->assertSessionHasErrors('student_photo');

        $this->assertDatabaseCount('coaching_admissions', 0);
        $this->assertDatabaseCount('profile_documents', 0);
    }

    public function test_approval_generates_required_admission_fee_and_enrollment_activates_only_after_full_payment(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        [$branch, $course, $batch] = $this->setupCoaching();
        $application = CoachingAdmission::create([
            'student_name' => 'Test Student', 'student_email' => 'student@example.com', 'student_phone' => '01700000000',
            'branch_id' => $branch->id, 'course_id' => $course->id, 'coaching_batch_id' => $batch->id,
        ]);

        $this->actingAs($admin)->post(route('admin.coaching-admissions.approve', $application), ['admin_note' => 'Approved'])->assertRedirect();

        $student = User::where('email', 'student@example.com')->firstOrFail();
        $application->refresh();
        $invoice = StudentFeeInvoice::where('coaching_admission_id', $application->id)->firstOrFail();

        $this->assertSame('awaiting_payment', $application->status);
        $this->assertSame('admission', $invoice->invoice_type);
        $this->assertEquals(500, (float) $invoice->amount);
        $this->assertDatabaseCount('coaching_enrollments', 0);
        $this->assertDatabaseCount('enrollments', 0);

        $this->post(route('admin.coaching-fees.payments.store', $invoice), ['amount' => 500, 'method' => 'cash'])
            ->assertRedirect();

        $this->assertSame('admitted', $application->fresh()->status);
        $this->assertDatabaseHas('coaching_enrollments', [
            'coaching_admission_id' => $application->id,
            'student_id' => $student->id,
            'coaching_batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_zero_admission_fee_allows_admin_approval_to_activate_physical_coaching_enrollment(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        [$branch, $course, $batch] = $this->setupCoaching();
        $batch->update(['admission_fee' => 0]);
        $application = CoachingAdmission::create([
            'student_name' => 'No Fee Student', 'student_email' => 'nofee@example.com', 'student_phone' => '01700000001',
            'branch_id' => $branch->id, 'course_id' => $course->id, 'coaching_batch_id' => $batch->id,
        ]);

        $this->actingAs($admin)->post(route('admin.coaching-admissions.approve', $application))->assertRedirect();

        $this->assertSame('admitted', $application->fresh()->status);
        $this->assertDatabaseHas('coaching_enrollments', ['coaching_admission_id' => $application->id, 'status' => 'active']);
        $this->assertDatabaseCount('student_fee_invoices', 0);
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
    public function test_approval_links_an_existing_guardian_account_to_the_student_profile(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $guardian = User::create(['name' => 'Guardian', 'email' => 'parent@example.com', 'password' => 'password', 'role' => 'guardian', 'is_active' => true]);
        [$branch, $course, $batch] = $this->setupCoaching();
        $batch->update(['admission_fee' => 0]);
        $application = CoachingAdmission::create([
            'student_name' => 'Child Student', 'student_email' => 'child@example.com', 'student_phone' => '01700000000',
            'guardian_name' => 'Guardian', 'guardian_email' => $guardian->email,
            'branch_id' => $branch->id, 'course_id' => $course->id, 'coaching_batch_id' => $batch->id,
        ]);

        $this->actingAs($admin)->post(route('admin.coaching-admissions.approve', $application))->assertRedirect();

        $student = User::where('email', 'child@example.com')->firstOrFail();
        $this->assertTrue($guardian->children()->whereKey($student->id)->exists());
    }

}
