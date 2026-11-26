<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LiveClass;
use App\Models\User;
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
}
