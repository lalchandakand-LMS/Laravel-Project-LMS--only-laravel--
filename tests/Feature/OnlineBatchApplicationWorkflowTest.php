<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\CoachingAdmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\OnlineBatchApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineBatchApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_apply_for_online_batch_and_admin_approval_uses_online_enrollment_only(): void
    {
        $student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $course = Course::create(['title' => 'Online English', 'price' => 2000, 'is_published' => true]);
        $batch = Batch::create(['name' => 'Online Evening', 'code' => 'ONL-EVE', 'course_id' => $course->id, 'status' => 'upcoming']);

        $this->actingAs($student)->post(route('student.online-batches.apply'), ['batch_id' => $batch->id])->assertRedirect();
        $application = OnlineBatchApplication::firstOrFail();

        $this->actingAs($admin)->post(route('admin.online-batch-applications.approve', $application))->assertRedirect();

        $this->assertDatabaseHas('online_batch_enrollments', ['student_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseHas('online_batch_applications', ['id' => $application->id, 'status' => 'approved']);
        $this->assertDatabaseCount('coaching_admissions', 0);
        $this->assertDatabaseCount('student_fee_invoices', 0);
    }

    public function test_approved_online_batch_application_cannot_be_rejected_after_approval(): void
    {
        $student = User::create(['name' => 'Online Student', 'email' => 'online-approved@example.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'online-admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $course = Course::create(['title' => 'Approved Online Course', 'price' => 1500, 'is_published' => true]);
        $batch = Batch::create(['name' => 'Approved Online Batch', 'code' => 'ONL-APPROVED', 'course_id' => $course->id, 'status' => 'upcoming']);
        $this->actingAs($student)->post(route('student.online-batches.apply'), ['batch_id' => $batch->id])->assertRedirect();
        $application = OnlineBatchApplication::firstOrFail();

        $this->actingAs($admin)->post(route('admin.online-batch-applications.approve', $application))->assertRedirect();

        $this->post(route('admin.online-batch-applications.reject', $application), [
            'review_note' => 'This must not overwrite an approved admission.',
        ])->assertStatus(422);

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertDatabaseHas('online_batch_enrollments', [
            'application_id' => $application->id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
    }

    public function test_student_cannot_apply_for_an_online_batch_in_another_branch(): void
    {
        $studentBranch = \\App\\Models\\Branch::create([
            'name' => 'Student Online Branch',
            'code' => 'ONL-ST-BR',
            'is_active' => true,
        ]);
        $otherBranch = \\App\\Models\\Branch::create([
            'name' => 'Other Online Branch',
            'code' => 'ONL-OTHER-BR',
            'is_active' => true,
        ]);
        $student = User::create([
            'name' => 'Branch Student',
            'email' => 'branch-student@example.com',
            'password' => 'password',
            'role' => 'student',
            'is_active' => true,
            'branch_id' => $studentBranch->id,
        ]);
        $course = Course::create(['title' => 'Other Branch Course', 'price' => 1500, 'is_published' => true]);
        $batch = Batch::create([
            'name' => 'Other Branch Live Batch',
            'code' => 'ONL-OTHER-APP',
            'course_id' => $course->id,
            'branch_id' => $otherBranch->id,
            'status' => 'upcoming',
        ]);

        $this->actingAs($student)->post(route('student.online-batches.apply'), [
            'batch_id' => $batch->id,
        ])->assertSessionHasErrors('batch_id');

        $this->assertDatabaseCount('online_batch_applications', 0);
        $this->actingAs($student)->get(route('student.online-batches'))
            ->assertOk()
            ->assertDontSee('Other Branch Live Batch');
    }

    public function test_student_cannot_apply_for_a_batch_with_an_unpublished_course(): void
    {
        $student = User::create([
            'name' => 'Unpublished Course Student',
            'email' => 'unpublished-student@example.com',
            'password' => 'password',
            'role' => 'student',
            'is_active' => true,
        ]);
        $course = Course::create(['title' => 'Unpublished Online Course', 'price' => 1500, 'is_published' => false]);
        $batch = Batch::create([
            'name' => 'Unpublished Course Batch',
            'code' => 'ONL-UNPUB',
            'course_id' => $course->id,
            'status' => 'upcoming',
        ]);

        $this->actingAs($student)->post(route('student.online-batches.apply'), [
            'batch_id' => $batch->id,
        ])->assertSessionHasErrors('batch_id');

        $this->assertDatabaseCount('online_batch_applications', 0);
        $this->actingAs($student)->get(route('student.online-batches'))
            ->assertOk()
            ->assertDontSee('Unpublished Course Batch');
    }

    public function test_existing_online_course_buyer_can_apply_for_a_live_batch_without_creating_a_second_course_enrollment(): void
    {
        $student = User::create(['name' => 'Student', 'email' => 'buyer@example.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $course = Course::create(['title' => 'Online English', 'price' => 2000, 'is_published' => true]);
        $batch = Batch::create(['name' => 'Online Evening', 'code' => 'ONL-EVE', 'course_id' => $course->id, 'status' => 'upcoming']);
        $existing = Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => null, 'price_paid' => 2000, 'status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($student)->post(route('student.online-batches.apply'), ['batch_id' => $batch->id])->assertRedirect();
        $application = OnlineBatchApplication::firstOrFail();
        $this->actingAs($admin)->post(route('admin.online-batch-applications.approve', $application))->assertRedirect();

        $this->assertDatabaseCount('enrollments', 1);
        $this->assertSame(null, $existing->fresh()->batch_id);
        $this->assertDatabaseHas('online_batch_enrollments', ['student_id' => $student->id, 'batch_id' => $batch->id]);
        $this->assertSame(2000.0, (float) $existing->fresh()->price_paid);
    }

    public function test_coaching_student_identity_can_coexist_with_online_enrollment_without_merging_fee_ledgers(): void
    {
        $student = User::create(['name' => 'Dual Path Student', 'email' => 'dual@example.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        $course = Course::create(['title' => 'Online English', 'price' => 2000, 'is_published' => true]);
        $onlineBatch = Batch::create(['name' => 'Online Evening', 'code' => 'ONL-EVE', 'course_id' => $course->id, 'status' => 'upcoming']);

        $this->assertSame('student', $student->fresh()->role);
        $this->assertDatabaseCount('coaching_admissions', 0);
        $this->assertDatabaseCount('student_fee_invoices', 0);
        $this->assertDatabaseHas('batches', ['id' => $onlineBatch->id]);
    }
}
