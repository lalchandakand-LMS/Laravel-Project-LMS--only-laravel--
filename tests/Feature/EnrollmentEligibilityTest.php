<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_courses_only_shows_active_unexpired_and_batch_eligible_courses(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        $studentBranch = Branch::create([
            'name' => 'Student Branch',
            'code' => 'ELIG-ST',
            'is_active' => true,
        ]);
        $otherBranch = Branch::create([
            'name' => 'Other Branch',
            'code' => 'ELIG-OT',
            'is_active' => true,
        ]);
        $student->update(['branch_id' => $studentBranch->id]);

        $currentCourse = Course::create([
            'title' => 'Current Course',
            'slug' => 'current-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $expiredCourse = Course::create([
            'title' => 'Expired Course',
            'slug' => 'expired-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $cancelledCourse = Course::create([
            'title' => 'Cancelled Batch Course',
            'slug' => 'cancelled-batch-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $wrongBranchCourse = Course::create([
            'title' => 'Wrong Branch Course',
            'slug' => 'wrong-branch-course',
            'price' => 1000,
            'is_published' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $currentCourse->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(10),
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $expiredCourse->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now()->subDays(40),
            'expires_at' => now()->subMinute(),
        ]);

        $cancelledBatch = Batch::create([
            'course_id' => $cancelledCourse->id,
            'name' => 'Cancelled batch',
            'code' => 'ELIG-CANCEL',
            'status' => 'cancelled',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $cancelledCourse->id,
            'batch_id' => $cancelledBatch->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
        ]);

        $snapshotBranchCourse = Course::create([
            'title' => 'Old Branch Unbatched Course',
            'slug' => 'old-branch-unbatched-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $snapshotBranchCourse->id,
            'branch_id' => $otherBranch->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
        ]);

        $wrongBranchBatch = Batch::create([
            'course_id' => $wrongBranchCourse->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Other branch batch',
            'code' => 'ELIG-BRANCH',
            'status' => 'ongoing',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $wrongBranchCourse->id,
            'batch_id' => $wrongBranchBatch->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('student.courses'))
            ->assertOk()
            ->assertSee('Current Course')
            ->assertDontSee('Expired Course')
            ->assertDontSee('Cancelled Batch Course')
            ->assertDontSee('Wrong Branch Course')
            ->assertDontSee('Old Branch Unbatched Course');
    }

    public function test_user_course_helpers_ignore_expired_enrollments(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Expired Helper Course',
            'slug' => 'expired-helper-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now()->subDays(20),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse($student->hasCourse($course));
        $this->assertSame(0.0, $student->overallProgress());
    }
}
