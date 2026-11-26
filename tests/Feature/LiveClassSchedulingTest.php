<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\AttendanceSession;
use App\Models\LiveClass;
use App\Models\User;
use App\Notifications\LiveClassChanged;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveClassSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_create_update_and_delete_a_live_class(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();
        $startsAt = now()->addDays(4)->startOfHour();

        $this->actingAs($admin)->post(route('admin.live-classes.store'), [
            'course_id' => $course->id,
            'title' => 'Admin managed live session',
            'platform' => 'Zoom',
            'meeting_url' => 'https://zoom.us/j/123456789',
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'is_published' => 1,
        ])->assertRedirect(route('admin.live-classes.index'));

        $liveClass = LiveClass::where('title', 'Admin managed live session')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.live-classes.edit', $liveClass))->assertOk();

        $this->actingAs($admin)->put(route('admin.live-classes.update', $liveClass), [
            'course_id' => $course->id,
            'title' => 'Updated live session',
            'platform' => 'Google Meet',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'starts_at' => $startsAt->copy()->addDay()->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'is_published' => 1,
        ])->assertRedirect(route('admin.live-classes.index'));

        $this->assertDatabaseHas('live_classes', [
            'id' => $liveClass->id,
            'title' => 'Updated live session',
            'platform' => 'Google Meet',
            'duration_minutes' => 45,
        ]);

        $this->actingAs($admin)->delete(route('admin.live-classes.destroy', $liveClass))
            ->assertRedirect(route('admin.live-classes.index'));
    }

    public function test_admin_cannot_schedule_overlapping_sessions_for_the_same_teacher(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $course = Course::firstOrFail();
        $course->update(['instructor_id' => $teacher->id]);

        $startsAt = now()->addDays(5)->startOfDay()->addHours(10);

        LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Existing teacher session',
            'platform' => 'Zoom',
            'meeting_url' => 'https://zoom.us/j/111111111',
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($admin)->from(route('admin.live-classes.create'))
            ->post(route('admin.live-classes.store'), [
                'course_id' => $course->id,
                'title' => 'Conflicting teacher session',
                'platform' => 'Google Meet',
                'meeting_url' => 'https://meet.google.com/abc-defg-hij',
                'starts_at' => $startsAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
                'duration_minutes' => 60,
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.live-classes.create'))
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseMissing('live_classes', [
            'title' => 'Conflicting teacher session',
        ]);
    }

    public function test_live_class_platform_must_be_supported(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();

        $this->actingAs($admin)->post(route('admin.live-classes.store'), [
            'course_id' => $course->id,
            'title' => 'Unsupported platform session',
            'platform' => 'Unknown Meeting App',
            'meeting_url' => 'https://example.com/meeting',
            'starts_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'is_published' => 1,
        ])->assertSessionHasErrors('platform');

        $this->assertDatabaseMissing('live_classes', [
            'title' => 'Unsupported platform session',
        ]);
    }

    public function test_batch_live_class_is_visible_only_to_active_members_of_that_batch(): void
    {
        $course = Course::firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $batch = Batch::create([
            'course_id' => $course->id,
            'name' => 'Evening Batch',
            'code' => 'EVENING-'.strtoupper(\Illuminate\Support\Str::random(5)),
            'status' => 'active',
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $otherBatch = Batch::create([
            'course_id' => $course->id,
            'name' => 'Morning Batch',
            'code' => 'MORNING-'.strtoupper(\Illuminate\Support\Str::random(5)),
            'status' => 'active',
        ]);
        Enrollment::create([
            'user_id' => $otherStudent->id,
            'course_id' => $course->id,
            'batch_id' => $otherBatch->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        LiveClass::create([
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'title' => 'Private batch revision',
            'platform' => 'Zoom',
            'meeting_url' => 'https://zoom.us/j/987654321',
            'starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($student)->get(route('student.live-classes'))
            ->assertOk()
            ->assertSee('Private batch revision');

        $this->actingAs($otherStudent)->get(route('student.live-classes'))
            ->assertOk()
            ->assertDontSee('Private batch revision');
    }


    public function test_admin_can_cancel_live_class_and_notify_enrolled_students(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $guardian = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        $course = Course::firstOrFail();

        DB::table('guardian_students')->insert([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relation' => 'parent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Cancellation notification test',
            'platform' => 'Zoom',
            'meeting_url' => 'https://zoom.us/j/123456789',
            'starts_at' => now()->addDays(8),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.live-classes.cancel', $liveClass), [
            'cancellation_reason' => 'Teacher unavailable',
        ])->assertRedirect(route('admin.live-classes.index'));

        $this->assertNotNull($liveClass->fresh()->cancelled_at);
        $this->assertFalse($liveClass->fresh()->canJoin());
        Notification::assertSentTo($student, LiveClassChanged::class);
        Notification::assertSentTo($guardian, LiveClassChanged::class, fn ($notification) =>
            $notification->toDatabase($guardian)['url'] === route('guardian.dashboard')
        );
    }

    public function test_cancelled_live_class_can_be_rescheduled_and_reactivated(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Reschedule workflow test',
            'platform' => 'Zoom',
            'starts_at' => now()->addDays(9),
            'duration_minutes' => 60,
            'is_published' => true,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Previous date unavailable',
        ]);

        $newStart = now()->addDays(12)->startOfHour();
        $this->actingAs($admin)->post(route('admin.live-classes.reschedule', $liveClass), [
            'starts_at' => $newStart->format('Y-m-d H:i:s'),
            'duration_minutes' => 75,
        ])->assertRedirect(route('admin.live-classes.index'));

        $liveClass->refresh();
        $this->assertNull($liveClass->cancelled_at);
        $this->assertNull($liveClass->cancellation_reason);
        $this->assertSame(75, (int) $liveClass->duration_minutes);
        $this->assertSame($newStart->timestamp, $liveClass->starts_at->timestamp);
        Notification::assertSentTo($student, LiveClassChanged::class);
    }

    public function test_live_class_create_and_edit_forms_receive_batch_options(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.live-classes.create'))->assertOk();
    }


    public function test_batch_live_class_can_create_one_linked_attendance_session(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();
        $batch = Batch::create([
            'course_id' => $course->id,
            'name' => 'Attendance Batch',
            'code' => 'ATTEND-'.strtoupper(\Illuminate\Support\Str::random(5)),
            'status' => 'active',
        ]);
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'title' => 'Attendance-linked live class',
            'platform' => 'Zoom',
            'starts_at' => now()->addDays(15),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($admin)->post(route('teacher.live-classes.attendance', $liveClass))
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_sessions', [
            'live_class_id' => $liveClass->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'mode' => 'online',
        ]);

        $this->actingAs($admin)->post(route('teacher.live-classes.attendance', $liveClass))
            ->assertRedirect();

        $this->assertSame(1, AttendanceSession::where('live_class_id', $liveClass->id)->count());

        $liveClass->update(['cancelled_at' => now()]);
        $newStart = now()->addDays(20)->startOfHour();
        $this->actingAs($admin)->post(route('admin.live-classes.reschedule', $liveClass), [
            'starts_at' => $newStart->format('Y-m-d H:i:s'),
            'duration_minutes' => 90,
        ])->assertRedirect(route('admin.live-classes.index'));

        $attendanceSession = AttendanceSession::where('live_class_id', $liveClass->id)->firstOrFail();
        $this->assertSame($newStart->timestamp, $attendanceSession->starts_at->timestamp);
        $this->assertEquals(90, $attendanceSession->starts_at->diffInMinutes($attendanceSession->ends_at));

        $attendanceSession->update(['status' => 'approved']);
        $liveClass->update(['cancelled_at' => now()]);
        $this->actingAs($admin)->post(route('admin.live-classes.reschedule', $liveClass), [
            'starts_at' => now()->addDays(22)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
        ])->assertStatus(422);
    }


    public function test_course_wide_live_class_creates_attendance_for_course_enrollments(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'batch_id' => null,
            'title' => 'Course-wide attendance class',
            'platform' => 'Zoom',
            'starts_at' => now()->addDays(25),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($admin)->post(route('teacher.live-classes.attendance', $liveClass))
            ->assertRedirect();

        $session = AttendanceSession::where('live_class_id', $liveClass->id)->firstOrFail();
        $this->assertNull($session->batch_id);
        $this->actingAs($admin)->get(route('teacher.attendance.show', $session))
            ->assertOk()
            ->assertSee($student->name);
    }


    public function test_admin_can_view_monthly_live_class_calendar(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();
        LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Calendar display test',
            'platform' => 'Zoom',
            'starts_at' => now()->startOfMonth()->addDays(4),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.live-classes.calendar', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Calendar display test');
    }


    public function test_student_can_view_monthly_calendar_for_eligible_live_classes(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::firstOrFail();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Student calendar session',
            'platform' => 'Zoom',
            'starts_at' => now()->startOfMonth()->addDays(3)->setHour(11),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($student)
            ->get(route('student.live-classes', ['view' => 'calendar', 'month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Student calendar session')
            ->assertSee('Calendar');
    }

    public function test_teacher_calendar_only_shows_owned_course_live_classes(): void
    {
        $teacher = User::where('email', 'teacher1@lms.test')->firstOrFail();
        $course = Course::firstOrFail();
        $course->update(['instructor_id' => $teacher->id]);
        LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Teacher calendar session',
            'platform' => 'Google Meet',
            'starts_at' => now()->startOfMonth()->addDays(5)->setHour(12),
            'duration_minutes' => 45,
            'is_published' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.live-classes', ['view' => 'calendar', 'month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Teacher calendar session');
    }

    public function test_live_class_email_notifications_are_opt_in(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $liveClass = LiveClass::create([
            'course_id' => Course::firstOrFail()->id,
            'title' => 'Email opt-in session',
            'platform' => 'Zoom',
            'starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);
        $notification = new LiveClassChanged($liveClass, 'scheduled');

        config(['live_classes.email_notifications' => false]);
        $this->assertSame(['database'], $notification->via($student));

        config(['live_classes.email_notifications' => true]);
        $this->assertSame(['database', 'mail'], $notification->via($student));
    }

}
