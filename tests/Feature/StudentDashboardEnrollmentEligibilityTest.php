<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Branch;
use App\Models\Batch;
use App\Models\CoachingAdmission;
use App\Models\CoachingBatch;
use App\Models\CoachingEnrollment;
use App\Models\OnlineBatchApplication;
use App\Models\OnlineBatchEnrollment;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardEnrollmentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_only_lists_exams_for_active_unexpired_enrollments(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $activeCourse = Course::create([
            'title' => 'Active Dashboard Course',
            'slug' => 'active-dashboard-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $expiredCourse = Course::create([
            'title' => 'Expired Dashboard Course',
            'slug' => 'expired-dashboard-course',
            'price' => 1000,
            'is_published' => true,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $activeCourse->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now()->subDay(),
            'expires_at' => now()->addDays(5),
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $expiredCourse->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now()->subDays(40),
            'expires_at' => now()->subMinute(),
        ]);

        Exam::create([
            'course_id' => $activeCourse->id,
            'title' => 'Available Course Exam',
            'duration_minutes' => 30,
            'total_marks' => 20,
            'pass_marks' => 10,
            'is_free' => false,
            'is_published' => true,
        ]);
        Exam::create([
            'course_id' => $expiredCourse->id,
            'title' => 'Expired Course Exam',
            'duration_minutes' => 30,
            'total_marks' => 20,
            'pass_marks' => 10,
            'is_free' => false,
            'is_published' => true,
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Available Course Exam')
            ->assertDontSee('Expired Course Exam')
            ->assertDontSee('Expired Dashboard Course');
    }
    public function test_dashboard_hides_ended_cancelled_and_other_branch_batch_enrollments(): void
    {
        $studentBranch = Branch::create(['name' => 'Dashboard Student Branch', 'code' => 'DASH-ST', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Dashboard Other Branch', 'code' => 'DASH-OT', 'is_active' => true]);
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'branch_id' => $studentBranch->id,
        ]);

        $onlineCourse = Course::create([
            'title' => 'Visible Online Batch Course',
            'slug' => 'visible-online-batch-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $otherOnlineCourse = Course::create([
            'title' => 'Other Branch Online Course',
            'slug' => 'other-branch-online-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $endedOnlineCourse = Course::create([
            'title' => 'Ended Online Course',
            'slug' => 'ended-online-course',
            'price' => 1000,
            'is_published' => true,
        ]);

        $onlineBatch = Batch::create([
            'course_id' => $onlineCourse->id,
            'branch_id' => $studentBranch->id,
            'name' => 'Visible Online Batch',
            'code' => 'DASH-ONL-OK',
            'status' => 'ongoing',
        ]);
        $otherOnlineBatch = Batch::create([
            'course_id' => $otherOnlineCourse->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Other Branch Online Batch',
            'code' => 'DASH-ONL-OTHER',
            'status' => 'ongoing',
        ]);
        $endedOnlineBatch = Batch::create([
            'course_id' => $endedOnlineCourse->id,
            'branch_id' => $studentBranch->id,
            'name' => 'Ended Online Batch',
            'code' => 'DASH-ONL-END',
            'status' => 'ongoing',
        ]);

        $onlineApplication = OnlineBatchApplication::create([
            'student_id' => $student->id,
            'course_id' => $onlineCourse->id,
            'batch_id' => $onlineBatch->id,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $otherOnlineApplication = OnlineBatchApplication::create([
            'student_id' => $student->id,
            'course_id' => $otherOnlineCourse->id,
            'batch_id' => $otherOnlineBatch->id,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $endedOnlineApplication = OnlineBatchApplication::create([
            'student_id' => $student->id,
            'course_id' => $endedOnlineCourse->id,
            'batch_id' => $endedOnlineBatch->id,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        OnlineBatchEnrollment::create([
            'application_id' => $onlineApplication->id,
            'student_id' => $student->id,
            'course_id' => $onlineCourse->id,
            'batch_id' => $onlineBatch->id,
            'status' => 'active',
            'admitted_at' => now(),
        ]);
        OnlineBatchEnrollment::create([
            'application_id' => $otherOnlineApplication->id,
            'student_id' => $student->id,
            'course_id' => $otherOnlineCourse->id,
            'batch_id' => $otherOnlineBatch->id,
            'status' => 'active',
            'admitted_at' => now(),
        ]);
        OnlineBatchEnrollment::create([
            'application_id' => $endedOnlineApplication->id,
            'student_id' => $student->id,
            'course_id' => $endedOnlineCourse->id,
            'batch_id' => $endedOnlineBatch->id,
            'status' => 'active',
            'admitted_at' => now()->subDays(5),
            'ended_at' => now()->subDay(),
        ]);

        $coachingCourse = Course::create([
            'title' => 'Visible Coaching Course',
            'slug' => 'visible-coaching-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $otherCoachingCourse = Course::create([
            'title' => 'Other Branch Coaching Course',
            'slug' => 'other-branch-coaching-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $coachingBatch = CoachingBatch::create([
            'name' => 'Visible Coaching Batch',
            'code' => 'DASH-COACH-OK',
            'branch_id' => $studentBranch->id,
            'course_id' => $coachingCourse->id,
            'status' => 'ongoing',
        ]);
        $otherCoachingBatch = CoachingBatch::create([
            'name' => 'Other Branch Coaching Batch',
            'code' => 'DASH-COACH-OTHER',
            'branch_id' => $otherBranch->id,
            'course_id' => $otherCoachingCourse->id,
            'status' => 'ongoing',
        ]);
        $coachingAdmission = CoachingAdmission::create([
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '01711112222',
            'student_id' => $student->id,
            'branch_id' => $studentBranch->id,
            'course_id' => $coachingCourse->id,
            'coaching_batch_id' => $coachingBatch->id,
            'status' => 'admitted',
            'admitted_at' => now(),
        ]);
        $otherCoachingAdmission = CoachingAdmission::create([
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '01711112222',
            'student_id' => $student->id,
            'branch_id' => $otherBranch->id,
            'course_id' => $otherCoachingCourse->id,
            'coaching_batch_id' => $otherCoachingBatch->id,
            'status' => 'admitted',
            'admitted_at' => now(),
        ]);
        CoachingEnrollment::create([
            'coaching_admission_id' => $coachingAdmission->id,
            'student_id' => $student->id,
            'coaching_batch_id' => $coachingBatch->id,
            'course_id' => $coachingCourse->id,
            'status' => 'active',
            'admitted_at' => now(),
        ]);
        CoachingEnrollment::create([
            'coaching_admission_id' => $otherCoachingAdmission->id,
            'student_id' => $student->id,
            'coaching_batch_id' => $otherCoachingBatch->id,
            'course_id' => $otherCoachingCourse->id,
            'status' => 'active',
            'admitted_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Visible Online Batch')
            ->assertDontSee('Other Branch Online Batch')
            ->assertDontSee('Ended Online Batch')
            ->assertSee('Visible Coaching Batch')
            ->assertDontSee('Other Branch Coaching Batch');
    }

    public function test_dashboard_hides_exams_attached_to_unpublished_courses(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Unpublished Dashboard Course',
            'slug' => 'unpublished-dashboard-course',
            'price' => 1000,
            'is_published' => false,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
        ]);

        Exam::create([
            'course_id' => $course->id,
            'title' => 'Hidden Unpublished Course Exam',
            'duration_minutes' => 30,
            'total_marks' => 20,
            'pass_marks' => 10,
            'is_free' => false,
            'is_published' => true,
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Hidden Unpublished Course Exam');
    }

}
