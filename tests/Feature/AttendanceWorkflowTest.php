<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\CoachingBatch;
use App\Models\CoachingEnrollment;
use App\Models\OnlineBatchApplication;
use App\Models\OnlineBatchEnrollment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupClassroom(): array
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['title' => 'Attendance English', 'slug' => 'attendance-english', 'instructor_id' => $teacher->id]);
        $batch = Batch::create([
            'course_id' => $course->id,
            'instructor_id' => $teacher->id,
            'name' => 'Morning batch',
            'code' => 'ATT-MORNING',
            'status' => 'ongoing',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
            'price_paid' => 0,
            'enrolled_at' => now(),
        ]);

        return [$teacher, $student, $course, $batch];
    }

    public function test_teacher_can_record_submit_and_admin_can_approve_attendance(): void
    {
        [$teacher, $student, $course, $batch] = $this->setupClassroom();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $guardian = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        DB::table('guardian_students')->insert([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relation' => 'father',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($teacher)->get(route('teacher.attendance.index'))->assertOk();
        $this->actingAs($teacher)->post(route('teacher.attendance.store'), [
            'batch_id' => $batch->id,
            'mode' => 'offline',
            'topic' => 'Speaking practice',
            'starts_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $session = AttendanceSession::firstOrFail();
        $this->actingAs($teacher)->post(route('teacher.attendance.save', $session), [
            'attendance' => [$student->id => ['status' => 'present', 'note' => 'On time']],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        $this->actingAs($teacher)->post(route('teacher.attendance.submit', $session))->assertRedirect();
        $this->assertSame('submitted', $session->fresh()->status);

        $this->actingAs($admin)->get(route('admin.attendance.index'))->assertOk();
        $this->actingAs($admin)->post(route('admin.attendance.review', $session), [
            'action' => 'approve',
        ])->assertRedirect();
        $this->assertSame('approved', $session->fresh()->status);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame('attendance', $student->notifications()->firstOrFail()->data['type']);
        $this->assertSame('present', $student->notifications()->firstOrFail()->data['attendance_status']);
        $this->assertSame('attendance', $guardian->notifications()->firstOrFail()->data['type']);

        $this->actingAs($student)->get(route('student.attendance'))
            ->assertOk()->assertSee('Speaking practice')->assertSee('Present');
    }

    public function test_teacher_cannot_submit_attendance_after_the_active_student_list_changes(): void
    {
        [$teacher, $originalStudent, $course, $batch] = $this->setupClassroom();
        $session = AttendanceSession::create([
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'instructor_id' => $teacher->id,
            'mode' => 'online',
            'starts_at' => now(),
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)->post(route('teacher.attendance.save', $session), [
            'attendance' => [$originalStudent->id => ['status' => 'present']],
        ])->assertRedirect();

        Enrollment::where('user_id', $originalStudent->id)
            ->where('course_id', $course->id)
            ->update(['status' => 'cancelled']);

        $replacementStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Enrollment::create([
            'user_id' => $replacementStudent->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
            'price_paid' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($teacher)->post(route('teacher.attendance.submit', $session))
            ->assertStatus(422);

        $this->assertSame('draft', $session->fresh()->status);
        $this->assertDatabaseMissing('attendances', [
            'attendance_session_id' => $session->id,
            'student_id' => $replacementStudent->id,
        ]);
    }

    public function test_teacher_cannot_create_attendance_for_another_teachers_batch(): void
    {
        [$teacher, $student, $course, $batch] = $this->setupClassroom();
        $otherTeacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);

        $this->actingAs($otherTeacher)->post(route('teacher.attendance.store'), [
            'batch_id' => $batch->id,
            'mode' => 'online',
            'starts_at' => now()->format('Y-m-d H:i:s'),
        ])->assertForbidden();

        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_guardian_only_sees_approved_attendance_for_linked_child(): void
    {
        [$teacher, $student, $course, $batch] = $this->setupClassroom();
        $guardian = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        \DB::table('guardian_students')->insert([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relation' => 'father',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = AttendanceSession::create([
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'instructor_id' => $teacher->id,
            'mode' => 'coaching',
            'starts_at' => now(),
            'status' => 'submitted',
        ]);
        Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'late',
            'marked_by' => $teacher->id,
        ]);

        $this->actingAs($guardian)->get(route('guardian.child.attendance', $student))
            ->assertOk()->assertSee('No approved attendance records yet.');
        $this->actingAs($guardian)->get(route('guardian.child.attendance', $otherStudent))->assertForbidden();

        $session->update(['status' => 'approved']);
        $this->actingAs($guardian)->get(route('guardian.child.attendance', $student))
            ->assertOk()->assertSee('Late');
    }

    public function test_attendance_roster_excludes_expired_and_cross_branch_legacy_enrollments(): void
    {
        [$teacher, $movedStudent, $course, $batch] = $this->setupClassroom();
        $studentBranch = Branch::create(['name' => 'Current Attendance Branch', 'code' => 'ATT-CURRENT', 'is_active' => true]);
        $batchBranch = Branch::create(['name' => 'Assigned Attendance Branch', 'code' => 'ATT-ASSIGNED', 'is_active' => true]);
        $movedStudent->update(['branch_id' => $studentBranch->id]);
        $batch->update(['branch_id' => $batchBranch->id]);
        Enrollment::where('user_id', $movedStudent->id)
            ->where('course_id', $course->id)
            ->update(['branch_id' => $studentBranch->id, 'expires_at' => now()->addDays(10)]);

        $expiredStudent = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'branch_id' => $batchBranch->id,
        ]);
        Enrollment::create([
            'user_id' => $expiredStudent->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'branch_id' => $batchBranch->id,
            'status' => 'active',
            'price_paid' => 0,
            'enrolled_at' => now()->subDays(40),
            'expires_at' => now()->subMinute(),
        ]);

        $eligibleStudent = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'branch_id' => $batchBranch->id,
        ]);
        Enrollment::create([
            'user_id' => $eligibleStudent->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'branch_id' => $batchBranch->id,
            'status' => 'active',
            'price_paid' => 0,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(10),
        ]);

        $session = AttendanceSession::create([
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'branch_id' => $batchBranch->id,
            'instructor_id' => $teacher->id,
            'mode' => 'offline',
            'starts_at' => now(),
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)->get(route('teacher.attendance.show', $session))
            ->assertOk()
            ->assertSee($eligibleStudent->name)
            ->assertDontSee($movedStudent->name)
            ->assertDontSee($expiredStudent->name);
    }

    public function test_attendance_roster_excludes_ended_coaching_and_online_enrollments(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Ended Attendance Course',
            'slug' => 'ended-attendance-course',
            'instructor_id' => $teacher->id,
        ]);

        $coachingBatch = CoachingBatch::create([
            'course_id' => $course->id,
            'instructor_id' => $teacher->id,
            'name' => 'Ended Coaching Batch',
            'code' => 'ATT-COACH-END',
            'status' => 'ongoing',
        ]);
        $admission = CoachingAdmission::create([
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '01711110000',
            'student_id' => $student->id,
            'course_id' => $course->id,
            'coaching_batch_id' => $coachingBatch->id,
            'status' => 'admitted',
            'admitted_at' => now()->subDays(20),
        ]);
        CoachingEnrollment::create([
            'coaching_admission_id' => $admission->id,
            'student_id' => $student->id,
            'coaching_batch_id' => $coachingBatch->id,
            'course_id' => $course->id,
            'status' => 'active',
            'admitted_at' => now()->subDays(20),
            'ended_at' => now()->subDay(),
        ]);
        $coachingSession = AttendanceSession::create([
            'coaching_batch_id' => $coachingBatch->id,
            'course_id' => $course->id,
            'instructor_id' => $teacher->id,
            'mode' => 'coaching',
            'topic' => 'Ended coaching roster',
            'starts_at' => now(),
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)->get(route('teacher.attendance.show', $coachingSession))
            ->assertOk()
            ->assertDontSee($student->name);

        $onlineBatch = Batch::create([
            'course_id' => $course->id,
            'instructor_id' => $teacher->id,
            'name' => 'Ended Online Batch',
            'code' => 'ATT-ONLINE-END',
            'status' => 'ongoing',
        ]);
        $application = OnlineBatchApplication::create([
            'student_id' => $student->id,
            'batch_id' => $onlineBatch->id,
            'course_id' => $course->id,
            'status' => 'approved',
            'approved_at' => now()->subDays(20),
        ]);
        OnlineBatchEnrollment::create([
            'application_id' => $application->id,
            'student_id' => $student->id,
            'batch_id' => $onlineBatch->id,
            'course_id' => $course->id,
            'status' => 'active',
            'admitted_at' => now()->subDays(20),
            'ended_at' => now()->subDay(),
        ]);
        $onlineSession = AttendanceSession::create([
            'batch_id' => $onlineBatch->id,
            'course_id' => $course->id,
            'instructor_id' => $teacher->id,
            'mode' => 'online',
            'topic' => 'Ended online roster',
            'starts_at' => now(),
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)->get(route('teacher.attendance.show', $onlineSession))
            ->assertOk()
            ->assertDontSee($student->name);
    }

    public function test_stale_admin_attendance_review_cannot_overwrite_an_approved_session(): void
    {
        [$teacher, $student, $course, $batch] = $this->setupClassroom();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $session = AttendanceSession::create([
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'instructor_id' => $teacher->id,
            'mode' => 'offline',
            'starts_at' => now(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'marked_by' => $teacher->id,
        ]);

        $staleSession = AttendanceSession::findOrFail($session->id);
        DB::table('attendance_sessions')->where('id', $session->id)->update([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $request = \Illuminate\\Http\\Request::create('/admin/attendance/'.$session->id.'/review', 'POST', [
            'action' => 'return',
            'review_note' => 'This stale review must not reverse the approval.',
        ]);
        $request->setUserResolver(fn () => $admin);

        try {
            app(\App\\Http\\Controllers\\AttendanceController::class)->review($request, $staleSession);
            $this->fail('A stale review must not overwrite an already approved attendance session.');
        } catch (\Symfony\\Component\\HttpKernel\\Exception\\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('approved', $session->fresh()->status);
        $this->assertSame($admin->id, $session->fresh()->reviewed_by);
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_stale_attendance_save_cannot_reopen_a_submitted_session(): void
    {
        [$teacher, $student, $course, $batch] = $this->setupClassroom();
        $session = AttendanceSession::create([
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'instructor_id' => $teacher->id,
            'mode' => 'online',
            'starts_at' => now(),
            'status' => 'draft',
        ]);
        Attendance::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'marked_by' => $teacher->id,
        ]);

        // Keep a stale model as if a save request loaded it just before submit committed.
        $staleSession = AttendanceSession::findOrFail($session->id);
        DB::table('attendance_sessions')->where('id', $session->id)->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $request = \Illuminate\Http\Request::create('/teacher/attendance/'.$session->id.'/save', 'POST', [
            'attendance' => [$student->id => ['status' => 'absent', 'note' => 'stale update']],
        ]);
        $request->setUserResolver(fn () => $teacher);

        try {
            app(\App\Http\Controllers\AttendanceController::class)->save($request, $staleSession);
            $this->fail('A stale save must not modify a submitted attendance session.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('submitted', $session->fresh()->status);
        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);
    }

}
