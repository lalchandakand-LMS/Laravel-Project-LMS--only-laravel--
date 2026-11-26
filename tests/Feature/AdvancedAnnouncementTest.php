<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvancedAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_announcement_with_branch_targeting_and_private_attachments(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Dhaka Branch', 'code' => 'DHK', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Exam routine published',
            'body' => "## Exam routine\nPlease **download** the attached file.",
            'branch_id' => $branch->id,
            'audience_role' => 'student',
            'priority' => 'important',
            'is_published' => '1',
            'attachments' => [UploadedFile::fake()->create('routine.pdf', 120, 'application/pdf')],
        ])->assertRedirect(route('admin.announcements.index'))->assertSessionHasNoErrors();

        $announcement = Announcement::firstOrFail();
        $this->assertSame($branch->id, $announcement->branch_id);
        $this->assertDatabaseHas('announcement_deliveries', ['announcement_id' => $announcement->id, 'user_id' => $student->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $student->id]);
        $attachment = $announcement->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($student)->get(route('notices.show', $announcement))
            ->assertOk()->assertSee('Exam routine')->assertSee('download');
        $this->actingAs($student)->get(route('notices.attachments.download', $attachment))
            ->assertOk();
    }

    public function test_editing_a_published_audience_notifies_only_new_recipients(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA', 'is_active' => true]);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB', 'is_active' => true]);
        $studentA = User::factory()->create(['role' => 'student', 'branch_id' => $branchA->id, 'is_active' => true]);
        $studentB = User::factory()->create(['role' => 'student', 'branch_id' => $branchB->id, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Branch update', 'body' => 'Read this update.',
            'branch_id' => $branchA->id, 'audience_role' => 'student',
            'is_published' => '1', 'priority' => 'general',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $announcement = Announcement::firstOrFail();

        $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), [
            'title' => $announcement->title,
            'body' => $announcement->body,
            'branch_id' => $branchB->id,
            'audience_role' => 'student',
            'priority' => 'general',
            'is_published' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('announcement_deliveries', ['announcement_id' => $announcement->id, 'user_id' => $studentA->id]);
        $this->assertDatabaseHas('announcement_deliveries', ['announcement_id' => $announcement->id, 'user_id' => $studentB->id]);
        $this->assertDatabaseCount('announcement_deliveries', 2);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_announcement_attachment_is_not_downloadable_by_an_unrelated_user(): void
    {
        Storage::fake('local');
        $branch = Branch::create(['name' => 'Private Branch', 'code' => 'PRV', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $other = User::factory()->create(['role' => 'student']);
        $announcement = Announcement::create([
            'title' => 'Private branch notice', 'body' => 'Private',
            'branch_id' => $branch->id, 'audience_role' => 'student', 'is_published' => true,
        ]);
        $path = UploadedFile::fake()->create('private.pdf', 20, 'application/pdf')->store('announcements/'.$announcement->id, 'local');
        $attachment = $announcement->attachments()->create([
            'uploaded_by' => $student->id, 'path' => $path, 'original_name' => 'private.pdf',
            'mime_type' => 'application/pdf', 'size' => 20480,
        ]);

        $this->actingAs($other)->get(route('notices.attachments.download', $attachment))->assertNotFound();
        $this->actingAs($student)->get(route('notices.attachments.download', $attachment))->assertOk();
    }

    public function test_admin_can_remove_an_existing_announcement_attachment(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $announcement = Announcement::create(['title' => 'Attachment removal', 'body' => 'Notice', 'is_published' => false]);
        $path = UploadedFile::fake()->create('old.pdf', 10, 'application/pdf')->store('announcements/'.$announcement->id, 'local');
        $attachment = $announcement->attachments()->create([
            'uploaded_by' => $admin->id, 'path' => $path, 'original_name' => 'old.pdf',
            'mime_type' => 'application/pdf', 'size' => 10240,
        ]);

        $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), [
            'title' => $announcement->title, 'body' => $announcement->body,
            'priority' => 'general', 'remove_attachments' => [$attachment->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('announcement_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
