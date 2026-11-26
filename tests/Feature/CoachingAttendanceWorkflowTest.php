<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\CoachingBatch;
use App\Models\CoachingEnrollment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingAttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_submit_physical_coaching_attendance_for_admin_review(): void
    {
        $teacher = User::create(['name' => 'Teacher', 'email' => 'teacher@example.com', 'password' => 'password', 'role' => 'instructor', 'is_active' => true]);
        $student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $course = Course::create(['title' => 'Spoken English', 'price' => 1000, 'is_published' => true]);
        $batch = CoachingBatch::create([
            'name' => 'Physical Morning', 'code' => 'PHY-MORN', 'branch_id' => $branch->id,
            'course_id' => $course->id, 'instructor_id' => $teacher->id, 'admission_fee' => 500,
            'monthly_fee' => 1000, 'status' => 'ongoing',
        ]);
        $admission = CoachingAdmission::create([
            'student_name' => $student->name, 'student_email' => $student->email, 'student_phone' => '01700000000',
            'branch_id' => $branch->id, 'course_id' => $course->id, 'coaching_batch_id' => $batch->id,
            'student_id' => $student->id, 'status' => 'admitted', 'admitted_at' => now(),
        ]);
        CoachingEnrollment::create([
            'coaching_admission_id' => $admission->id, 'student_id' => $student->id,
            'coaching_batch_id' => $batch->id, 'course_id' => $course->id,
            'status' => 'active', 'admitted_at' => now(),
        ]);

        $this->actingAs($teacher)->post(route('teacher.attendance.store'), [
            'coaching_batch_id' => $batch->id,
            'topic' => 'Unit 1',
            'starts_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $session = AttendanceSession::firstOrFail();
        $this->assertNull($session->batch_id);
        $this->assertSame($batch->id, $session->coaching_batch_id);
        $this->assertSame('coaching', $session->mode);

        $this->post(route('teacher.attendance.save', $session), [
            'attendance' => [$student->id => ['status' => 'present', 'note' => 'On time']],
        ])->assertRedirect();

        $this->post(route('teacher.attendance.submit', $session))->assertRedirect();
        $this->assertSame('submitted', $session->fresh()->status);
        $this->assertDatabaseHas('attendances', ['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present']);

        $this->actingAs($admin)->post(route('admin.attendance.review', $session), ['action' => 'approve'])
            ->assertRedirect();

        $this->assertSame('approved', $session->fresh()->status);
        $this->actingAs($student)->get(route('student.attendance'))->assertOk();
    }

    public function test_teacher_cannot_take_physical_attendance_for_another_teachers_coaching_batch(): void
    {
        $teacher = User::create(['name' => 'Teacher', 'email' => 'teacher@example.com', 'password' => 'password', 'role' => 'instructor', 'is_active' => true]);
        $otherTeacher = User::create(['name' => 'Other Teacher', 'email' => 'other@example.com', 'password' => 'password', 'role' => 'instructor', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $course = Course::create(['title' => 'Spoken English', 'price' => 1000, 'is_published' => true]);
        $batch = CoachingBatch::create([
            'name' => 'Physical Morning', 'code' => 'PHY-MORN', 'branch_id' => $branch->id,
            'course_id' => $course->id, 'instructor_id' => $otherTeacher->id, 'status' => 'ongoing',
        ]);

        $this->actingAs($teacher)->post(route('teacher.attendance.store'), [
            'coaching_batch_id' => $batch->id,
            'starts_at' => now()->format('Y-m-d H:i:s'),
        ])->assertForbidden();
        $this->assertDatabaseCount('attendance_sessions', 0);
    }
}
