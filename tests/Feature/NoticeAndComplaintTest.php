<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
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
        $this->assertDatabaseHas('complaint_events', ['complaint_id' => $complaint->id, 'event_type' => 'status']);
    }

    public function test_failed_complaint_attachment_upload_rolls_back_and_cleans_prior_files(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->twice()->andReturn('complaints/1/first.pdf', false);
        $disk->shouldReceive('delete')->once()->with('complaints/1/first.pdf')->andReturn(true);
        Storage::shouldReceive('disk')->times(3)->with('local')->andReturn($disk);

        $this->actingAs($student)->from(route('complaints.index'))->post(route('complaints.store'), [
            'category' => 'technical',
            'subject' => 'Attachment storage failure',
            'body' => 'The complaint should roll back if a private attachment cannot be saved.',
            'attachments' => [
                UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect(route('complaints.index'))
            ->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('complaints', 0);
        $this->assertDatabaseCount('complaint_events', 0);
        $this->assertDatabaseCount('complaint_attachments', 0);
    }

    public function test_failed_complaint_reply_attachment_does_not_create_a_reply_or_event(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $complaint = Complaint::create([
            'tracking_code' => 'AEC-REPLY-'.uniqid(),
            'user_id' => $student->id,
            'category' => 'technical',
            'subject' => 'Reply upload failure',
            'body' => 'An existing complaint for a failed reply upload.',
        ]);
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->twice()->andReturn('complaints/'.$complaint->id.'/first.pdf', false);
        $disk->shouldReceive('delete')->once()->with('complaints/'.$complaint->id.'/first.pdf')->andReturn(true);
        Storage::shouldReceive('disk')->times(3)->with('local')->andReturn($disk);

        $this->actingAs($student)->from(route('complaints.show', $complaint))
            ->post(route('complaints.reply', $complaint), [
                'body' => 'I am adding supporting evidence to this complaint.',
                'attachments' => [
                    UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('complaints.show', $complaint))
            ->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('complaint_replies', 0);
        $this->assertDatabaseCount('complaint_events', 0);
        $this->assertDatabaseCount('complaint_attachments', 0);
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
    public function test_guardian_sees_course_notice_for_a_linked_child_only(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $child = User::factory()->create(['role' => 'student']);
        DB::table('guardian_students')->insert([
            'guardian_id' => $guardian->id, 'student_id' => $child->id,
            'relation' => 'mother', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $course = Course::create(['title' => 'Child Course', 'slug' => 'child-course']);
        Enrollment::create(['user_id' => $child->id, 'course_id' => $course->id, 'status' => 'active']);
        Announcement::create([
            'course_id' => $course->id, 'audience_role' => 'guardian',
            'title' => 'Guardian course update', 'body' => 'A report is ready.', 'is_published' => true,
        ]);

        $this->actingAs($guardian)->get(route('notices.index'))
            ->assertOk()->assertSee('Guardian course update');
    }

    public function test_internal_complaint_notes_are_hidden_from_complainant(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $complaint = Complaint::create([
            'tracking_code' => 'AEC-TEST-87654321', 'user_id' => $student->id,
            'category' => 'other', 'subject' => 'Private note test', 'body' => 'A complaint body for internal note test.',
        ]);

        $this->actingAs($admin)->post(route('admin.complaints.reply', $complaint), [
            'body' => 'Confidential staff note', 'is_internal' => '1',
        ])->assertRedirect();

        $this->actingAs($student)->get(route('complaints.show', $complaint))
            ->assertOk()->assertDontSee('Confidential staff note');
        $this->actingAs($admin)->get(route('admin.complaints.show', $complaint))
            ->assertOk()->assertSee('Confidential staff note');
    }

}
