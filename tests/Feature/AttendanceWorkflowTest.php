<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Batch;
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
