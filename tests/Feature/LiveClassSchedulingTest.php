<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\AttendanceSession;
use App\Models\LiveClass;
use App\Models\User;
use App\Notifications\LiveClassChanged;
use App\Jobs\SendLiveClassSms;
use App\Jobs\SendLiveClassEmail;
use App\Services\Sms\MiMSmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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


    public function test_admin_update_syncs_linked_draft_attendance_schedule_and_roster_context(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();
        $batch = Batch::create([
            'course_id' => $course->id,
            'name' => 'Sync attendance batch',
            'code' => 'SYNC-ATT-'.strtoupper(\Illuminate\Support\Str::random(4)),
            'status' => 'ongoing',
        ]);
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'batch_id' => null,
            'title' => 'Original attendance topic',
            'platform' => 'Zoom',
            'starts_at' => now()->addDays(12),
            'duration_minutes' => 45,
            'is_published' => true,
        ]);
        $session = AttendanceSession::create([
            'live_class_id' => $liveClass->id,
            'course_id' => $course->id,
            'instructor_id' => $admin->id,
            'topic' => $liveClass->title,
            'starts_at' => $liveClass->starts_at,
            'ends_at' => $liveClass->endsAt(),
            'status' => 'draft',
            'mode' => 'online',
        ]);
        $newStart = now()->addDays(14)->startOfHour();

        $this->actingAs($admin)->put(route('admin.live-classes.update', $liveClass), [
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'title' => 'Updated attendance topic',
            'platform' => 'Google Meet',
            'starts_at' => $newStart->format('Y-m-d H:i:s'),
            'duration_minutes' => 90,
            'is_published' => 1,
        ])->assertRedirect(route('admin.live-classes.index'));

        $session->refresh();
        $this->assertSame($batch->id, $session->batch_id);
        $this->assertSame('Updated attendance topic', $session->topic);
        $this->assertSame($newStart->timestamp, $session->starts_at->timestamp);
        $this->assertEquals(90, $session->starts_at->diffInMinutes($session->ends_at));
    }

    public function test_admin_cannot_change_live_class_schedule_after_attendance_is_approved(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Approved attendance class',
            'platform' => 'Zoom',
            'starts_at' => now()->addDays(16),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);
        AttendanceSession::create([
            'live_class_id' => $liveClass->id,
            'course_id' => $course->id,
            'instructor_id' => $admin->id,
            'topic' => $liveClass->title,
            'starts_at' => $liveClass->starts_at,
            'ends_at' => $liveClass->endsAt(),
            'status' => 'approved',
            'mode' => 'online',
        ]);
        $oldStart = $liveClass->starts_at->timestamp;

        $this->actingAs($admin)->from(route('admin.live-classes.edit', $liveClass))
            ->put(route('admin.live-classes.update', $liveClass), [
                'course_id' => $course->id,
                'title' => $liveClass->title,
                'platform' => 'Zoom',
                'starts_at' => now()->addDays(18)->format('Y-m-d H:i:s'),
                'duration_minutes' => 60,
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.live-classes.edit', $liveClass))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame($oldStart, $liveClass->fresh()->starts_at->timestamp);
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

        config(['live_classes.email_notifications' => true, 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.username' => 'test-user', 'mail.mailers.smtp.password' => 'test-password', 'mail.from.address' => 'noreply@example.test']);
        $this->assertSame(['database', \App\Notifications\Channels\QueuedMailChannel::class], $notification->via($student));
    }


    public function test_live_class_sms_channel_is_opt_in_and_requires_a_phone_number(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'phone' => '01712345678',
        ]);
        $liveClass = LiveClass::create([
            'course_id' => Course::firstOrFail()->id,
            'title' => 'SMS opt-in session',
            'platform' => 'Zoom',
            'starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);
        $notification = new LiveClassChanged($liveClass, 'scheduled');

        config(['live_classes.sms_notifications' => false]);
        $this->assertSame(['database'], $notification->via($student));

        config(['live_classes.sms_notifications' => true, 'live_classes.sms.username' => 'panel@example.test', 'live_classes.sms.api_key' => 'test-key', 'live_classes.sms.sender_name' => 'ALOMS']);
        $this->assertContains(\App\Notifications\Channels\MiMSmsChannel::class, $notification->via($student));

        $student->forceFill(['phone' => null])->save();
        $this->assertNotContains(\App\Notifications\Channels\MiMSmsChannel::class, $notification->via($student));
    }

    public function test_mimsms_gateway_normalizes_bangladesh_numbers_and_sends_transactional_sms(): void
    {
        Http::fake([
            'api.mimsms.com/api/V2/SMS' => Http::response([
                'statusCode' => '200',
                'status' => 'Success',
                'trxnId' => 'TEST-TRACK-123',
            ], 200),
        ]);
        config([
            'live_classes.sms.username' => 'panel@example.test',
            'live_classes.sms.api_key' => 'test-api-key',
            'live_classes.sms.sender_name' => 'ALOMS',
            'live_classes.sms.endpoint' => 'https://api.mimsms.com/api/V2/SMS',
        ]);

        $gateway = app(MiMSmsGateway::class);
        $this->assertSame('8801712345678', $gateway->normalizeBangladeshNumber('+880 1712-345678'));
        $this->assertSame('8801712345678', $gateway->normalizeBangladeshNumber('01712345678'));
        $this->assertNull($gateway->normalizeBangladeshNumber('012345'));

        $result = $gateway->sendTransactional('01712345678', 'Live class starts tomorrow.');

        $this->assertSame('TEST-TRACK-123', $result['tracking_id']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.mimsms.com/api/V2/SMS'
            && $request['mobileNumber'] === '8801712345678'
            && $request['transactionType'] === 'T'
            && $request['apiKey'] === 'test-api-key');
    }

    public function test_live_class_sms_channel_dispatches_a_queued_job(): void
    {
        Queue::fake();
        config(['live_classes.sms_notifications' => true, 'live_classes.sms.username' => 'panel@example.test', 'live_classes.sms.api_key' => 'test-key', 'live_classes.sms.sender_name' => 'ALOMS']);

        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'phone' => '01712345678',
        ]);
        $liveClass = LiveClass::create([
            'course_id' => Course::firstOrFail()->id,
            'title' => 'Queued SMS session',
            'platform' => 'Zoom',
            'starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);
        $notification = new LiveClassChanged($liveClass, 'scheduled');
        $notification->id = 'notification-test-001';

        app(\App\Notifications\Channels\MiMSmsChannel::class)->send($student, $notification);

        Queue::assertPushed(SendLiveClassSms::class, fn ($job) => $job->userId === $student->id
            && $job->liveClassId === $liveClass->id
            && $job->event === 'scheduled');
    }


    public function test_live_class_email_channel_dispatches_a_queued_job(): void
    {
        Queue::fake();
        config([
            'live_classes.email_notifications' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'test-user',
            'mail.mailers.smtp.password' => 'test-password',
            'mail.from.address' => 'noreply@example.test',
        ]);

        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'email' => 'learner@example.test',
        ]);
        $liveClass = LiveClass::create([
            'course_id' => Course::firstOrFail()->id,
            'title' => 'Queued email session',
            'platform' => 'Zoom',
            'starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);
        $notification = new LiveClassChanged($liveClass, 'scheduled');
        $notification->id = 'notification-email-001';

        app(\App\Notifications\Channels\QueuedMailChannel::class)->send($student, $notification);

        Queue::assertPushed(SendLiveClassEmail::class, fn ($job) => $job->userId === $student->id
            && $job->liveClassId === $liveClass->id
            && $job->event === 'scheduled');
    }

    public function test_mimsms_gateway_throws_when_provider_rejects_request(): void
    {
        Http::fake([
            'api.mimsms.com/api/V2/SMS' => Http::response([
                'statusCode' => '417',
                'status' => 'Failed',
            ], 200),
        ]);
        config([
            'live_classes.sms.username' => 'panel@example.test',
            'live_classes.sms.api_key' => 'test-api-key',
            'live_classes.sms.sender_name' => 'ALOMS',
            'live_classes.sms.endpoint' => 'https://api.mimsms.com/api/V2/SMS',
        ]);

        $this->expectException(\RuntimeException::class);
        app(MiMSmsGateway::class)->sendTransactional('01712345678', 'Test message');
    }


    public function test_live_class_notifications_exclude_branch_ineligible_legacy_enrollments(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $studentBranch = \App\Models\Branch::create([
            'name' => 'Notification Student Branch',
            'code' => 'NOTIFY-ST',
            'is_active' => true,
        ]);
        $oldBranch = \App\Models\Branch::create([
            'name' => 'Notification Old Branch',
            'code' => 'NOTIFY-OLD',
            'is_active' => true,
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'branch_id' => $studentBranch->id,
        ]);
        $course = Course::firstOrFail();
        $batch = Batch::create([
            'course_id' => $course->id,
            'branch_id' => $studentBranch->id,
            'name' => 'Notification eligible batch',
            'code' => 'NOTIFY-BATCH',
            'status' => 'ongoing',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'branch_id' => $oldBranch->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.live-classes.store'), [
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'title' => 'Branch-ineligible notification class',
            'platform' => 'Zoom',
            'starts_at' => now()->addDays(22)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'is_published' => 1,
        ])->assertRedirect(route('admin.live-classes.index'));

        Notification::assertNotSentTo($student, LiveClassChanged::class);
    }

    public function test_live_class_notifications_exclude_online_enrollments_in_cancelled_batches(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::firstOrFail();
        $batch = Batch::create([
            'course_id' => $course->id,
            'name' => 'Cancelled online notification batch',
            'code' => 'NOTIFY-CANCELLED',
            'status' => 'cancelled',
        ]);
        $application = \App\Models\OnlineBatchApplication::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        \App\Models\OnlineBatchEnrollment::create([
            'application_id' => $application->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
            'admitted_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.live-classes.store'), [
            'course_id' => $course->id,
            'title' => 'Cancelled online batch notification class',
            'platform' => 'Zoom',
            'starts_at' => now()->addDays(23)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'is_published' => 1,
        ])->assertRedirect(route('admin.live-classes.index'));

        Notification::assertNotSentTo($student, LiveClassChanged::class);
    }

    public function test_publishing_an_existing_draft_live_class_notifies_eligible_students(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::firstOrFail();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        $startsAt = now()->addDays(30)->startOfHour();
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Draft live class to publish',
            'platform' => 'Zoom',
            'meeting_url' => 'https://zoom.us/j/123456789',
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
            'is_published' => false,
        ]);

        $this->actingAs($admin)->put(route('admin.live-classes.update', $liveClass), [
            'course_id' => $course->id,
            'title' => $liveClass->title,
            'platform' => 'Zoom',
            'meeting_url' => $liveClass->meeting_url,
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'is_published' => 1,
        ])->assertRedirect(route('admin.live-classes.index'));

        Notification::assertSentTo($student, LiveClassChanged::class, fn ($notification) =>
            $notification->event() === 'scheduled'
        );
    }

    public function test_changing_live_class_join_details_notifies_eligible_students(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::firstOrFail();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        $startsAt = now()->addDays(31)->startOfHour();
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Join details update',
            'platform' => 'Zoom',
            'meeting_url' => 'https://zoom.us/j/123456789',
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.live-classes.update', $liveClass), [
            'course_id' => $course->id,
            'title' => 'Join details update',
            'platform' => 'Google Meet',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'is_published' => 1,
        ])->assertRedirect(route('admin.live-classes.index'));

        Notification::assertSentTo($student, LiveClassChanged::class, fn ($notification) =>
            $notification->event() === 'updated'
        );
    }

    public function test_unpublishing_a_live_class_notifies_eligible_students(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::firstOrFail();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        $startsAt = now()->addDays(32)->startOfHour();
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Unpublish live class',
            'platform' => 'Zoom',
            'meeting_url' => 'https://zoom.us/j/123456789',
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.live-classes.update', $liveClass), [
            'course_id' => $course->id,
            'title' => $liveClass->title,
            'platform' => 'Zoom',
            'meeting_url' => $liveClass->meeting_url,
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'is_published' => 0,
        ])->assertRedirect(route('admin.live-classes.index'));

        Notification::assertSentTo($student, LiveClassChanged::class, fn ($notification) =>
            $notification->event() === 'unpublished'
        );
    }


    public function test_changing_only_the_live_class_meeting_password_notifies_eligible_students(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::firstOrFail();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        $startsAt = now()->addDays(33)->startOfHour();
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Password-only join update',
            'platform' => 'Zoom',
            'meeting_url' => 'https://zoom.us/j/123456789',
            'meeting_password' => 'old-password',
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.live-classes.update', $liveClass), [
            'course_id' => $course->id,
            'title' => $liveClass->title,
            'platform' => 'Zoom',
            'meeting_url' => $liveClass->meeting_url,
            'meeting_password' => 'new-password',
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'is_published' => 1,
        ])->assertRedirect(route('admin.live-classes.index'));

        Notification::assertSentTo($student, LiveClassChanged::class, fn ($notification) =>
            $notification->event() === 'updated'
        );
    }


}
