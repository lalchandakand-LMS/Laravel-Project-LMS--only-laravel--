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
}