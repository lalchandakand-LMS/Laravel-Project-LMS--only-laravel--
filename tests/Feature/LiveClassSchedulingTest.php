<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_live_class_lifecycle_notifies_only_enrolled_students_guardians_and_teacher(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $guardian = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        $outsider = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Live notice course',
            'slug' => 'live-notice-course',
            'instructor_id' => $teacher->id,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 0,
            'enrolled_at' => now(),
        ]);
        DB::table('guardian_students')->insert([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relation' => 'mother',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'course_id' => $course->id,
            'title' => 'Live notice session',
            'platform' => 'Zoom',
            'meeting_url' => 'https://zoom.us/j/123456789',
            'starts_at' => now()->addDays(4)->startOfHour()->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'is_published' => 1,
        ];

        $this->actingAs($admin)->post(route('admin.live-classes.store'), $payload)
            ->assertRedirect(route('admin.live-classes.index'));
        $liveClass = LiveClass::where('title', 'Live notice session')->firstOrFail();

        $this->assertDatabaseCount('notifications', 3);
        $this->assertSame('scheduled', $student->notifications()->firstOrFail()->data['event']);
        $this->assertSame(0, $outsider->notifications()->count());

        $payload['starts_at'] = now()->addDays(5)->startOfHour()->format('Y-m-d H:i:s');
        $this->actingAs($admin)->put(route('admin.live-classes.update', $liveClass), $payload)
            ->assertRedirect(route('admin.live-classes.index'));
        $this->assertSame(2, $student->notifications()->count());
        $this->assertSame('rescheduled', $student->notifications()->latest()->first()->data['event']);

        $this->actingAs($admin)->delete(route('admin.live-classes.destroy', $liveClass))
            ->assertRedirect(route('admin.live-classes.index'));
        $this->assertSame(3, $student->notifications()->count());
        $this->assertSame('cancelled', $student->notifications()->latest()->first()->data['event']);
        $this->assertSame(0, $outsider->notifications()->count());
    }

}
