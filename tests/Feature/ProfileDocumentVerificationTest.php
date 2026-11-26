<?php

namespace Tests\Feature;

use App\Models\ProfileDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileDocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_review_and_download_a_private_student_document(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $profile = $student->studentProfile()->create(['status' => 'active']);

        $this->actingAs($admin)->post(route('admin.users.documents.store', $student), [
            'document_type' => 'birth_certificate',
            'file' => UploadedFile::fake()->create('birth-certificate.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $document = ProfileDocument::firstOrFail();
        $this->assertSame('pending', $document->status);
        $this->assertSame($profile->getMorphClass(), $document->documentable_type);
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($admin)->post(route('admin.users.documents.review', [$student, $document]), [
            'status' => 'approved',
            'review_note' => 'Verified against original.',
        ])->assertRedirect();

        $this->assertSame('approved', $document->fresh()->status);
        $this->assertSame($admin->id, $document->fresh()->reviewed_by);

        $this->actingAs($admin)->get(route('admin.users.documents.download', [$student, $document]))
            ->assertOk();
    }

    public function test_profile_document_cannot_be_accessed_through_another_users_route(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $profile = $student->studentProfile()->create(['status' => 'active']);
        $document = $profile->documents()->create([
            'document_type' => 'nid',
            'disk' => 'local',
            'path' => 'profile-documents/1/nid.pdf',
            'original_name' => 'nid.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'status' => 'pending',
            'uploaded_by' => $admin->id,
        ]);
        Storage::disk('local')->put($document->path, 'private test document');

        $this->actingAs($admin)->get(route('admin.users.documents.download', [$otherStudent, $document]))
            ->assertNotFound();
    }

    public function test_upload_storage_failure_does_not_create_a_broken_document_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        $this->actingAs($admin)->from('/admin/users/'.$student->id.'/edit')
            ->post(route('admin.users.documents.store', $student), [
                'document_type' => 'nid',
                'file' => UploadedFile::fake()->image('nid.png'),
            ])
            ->assertRedirect('/admin/users/'.$student->id.'/edit')
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('profile_documents', 0);
    }

    public function test_non_admin_cannot_upload_or_review_profile_documents(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->post(route('admin.users.documents.store', $student), [
            'document_type' => 'nid',
            'file' => UploadedFile::fake()->image('nid.png'),
        ])->assertForbidden();
    }
}
