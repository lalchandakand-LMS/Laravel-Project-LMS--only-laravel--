<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeAndComplaintTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_global_and_enrolled_course_notices_but_not_other_course_notices(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create(['title' => 'Course A', 'slug' => 'course-a']);
        $other = Course::create(['title' => 'Course B', 'slug' => 'course-b']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
        Announcement::create(['title' => 'General notice', 'body' => 'Everyone should read this.', 'is_published' => true]);
        Announcement::create(['course_id' => $course->id, 'title' => 'My course notice', 'body' => 'Class update.', 'is_published' => true]);
        Announcement::create(['course_id' => $other->id, 'title' => 'Private other course', 'body' => 'Not for this student.', 'is_published' => true]);

        $this->actingAs($student)->get(route('notices.index'))
            ->assertOk()->assertSee('General notice')->assertSee('My course notice')->assertDontSee('Private other course');
    }

    public function test_notice_read_receipt_is_saved_for_authenticated_user(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $notice = Announcement::create(['title' => 'Read receipt', 'body' => 'Please read.', 'is_published' => true]);
        $this->actingAs($student)->get(route('notices.show', $notice))->assertOk();
        $this->assertDatabaseHas('announcement_reads', ['announcement_id' => $notice->id, 'user_id' => $student->id]);
    }

    public function test_user_can_submit_and_track_a_complaint_and_admin_can_update_it(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($student)->post(route('complaints.store'), [
            'category' => 'class', 'subject' => 'Class timing issue', 'body' => 'The class schedule is not matching the announced time.',
        ])->assertRedirect();

        $complaint = Complaint::firstOrFail();
        $this->assertSame($student->id, $complaint->user_id);
        $this->assertStringStartsWith('AEC-', $complaint->tracking_code);
        $this->actingAs($student)->get(route('complaints.show', $complaint))->assertOk()->assertSee($complaint->tracking_code);
        $this->actingAs($admin)->patch(route('admin.complaints.update', $complaint), [
            'status' => 'in_progress', 'priority' => 'high', 'assigned_to' => null,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $complaint->fresh()->status);
    }

    public function test_user_cannot_view_another_users_complaint(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $complaint = Complaint::create([
            'tracking_code' => 'AEC-TEST-12345678', 'user_id' => $owner->id,
            'category' => 'other', 'subject' => 'Private complaint', 'body' => 'A private complaint description.',
        ]);
        $this->actingAs($other)->get(route('complaints.show', $complaint))->assertForbidden();
    }

    public function test_notice_audience_role_and_expiry_are_enforced(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Announcement::create(['title' => 'Guardian only', 'body' => 'Parent information.', 'audience_role' => 'guardian', 'is_published' => true]);
        Announcement::create(['title' => 'Expired', 'body' => 'Old notice.', 'expires_at' => now()->subDay(), 'is_published' => true]);
        $this->actingAs($student)->get(route('notices.index'))->assertOk()->assertDontSee('Guardian only')->assertDontSee('Expired');
    }
}
