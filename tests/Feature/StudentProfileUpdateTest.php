<?php

namespace Tests\\Feature;

use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Http\\UploadedFile;
use Illuminate\\Support\\Facades\\Storage;
use Tests\\TestCase;

class StudentProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_photo_replacement_keeps_new_file_and_removes_old_file_after_success(): void
    {
        Storage::fake('public');
        $oldAvatar = 'avatars/old-profile.png';
        Storage::disk('public')->put($oldAvatar, 'old-image');

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student->forceFill([
            'avatar' => $oldAvatar,
            'phone' => '01700000000',
            'phone_verified_at' => now(),
        ])->save();

        $this->actingAs($student)
            ->put(route('student.profile.update'), [
                'name' => $student->name,
                'phone' => '01800000000',
                'bio' => 'Updated student profile',
                'avatar' => UploadedFile::fake()->image('new-profile.png'),
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertNotSame($oldAvatar, $student->avatar);
        $this->assertSame('01800000000', $student->phone);
        $this->assertNull($student->phone_verified_at);
        Storage::disk('public')->assertMissing($oldAvatar);
        Storage::disk('public')->assertExists($student->avatar);
    }

    public function test_profile_update_without_new_photo_preserves_existing_avatar(): void
    {
        Storage::fake('public');
        $avatar = 'avatars/keep-profile.png';
        Storage::disk('public')->put($avatar, 'existing-image');

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student->forceFill(['avatar' => $avatar])->save();

        $this->actingAs($student)
            ->put(route('student.profile.update'), [
                'name' => 'Updated Student Name',
                'bio' => 'Updated bio',
            ])
            ->assertRedirect();

        $this->assertSame($avatar, $student->fresh()->avatar);
        Storage::disk('public')->assertExists($avatar);
    }
}
