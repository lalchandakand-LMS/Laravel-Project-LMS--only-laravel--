<?php

namespace Tests\\Feature;

use App\\Models\\Course;
use App\\Models\\Lesson;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\Storage;
use RuntimeException;
use Tests\\TestCase;

class PrivateLearningFileMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function migration()
    {
        return require database_path('migrations/2026_11_25_000001_move_paid_learning_files_to_private_storage.php');
    }

    private function lessonWithAttachment(string $path): Lesson
    {
        $lesson = Course::whereHas('lessons')->firstOrFail()->lessons()->firstOrFail();
        $lesson->update(['attachment' => $path]);

        return $lesson->fresh();
    }

    public function test_up_verifies_private_copy_before_deleting_public_file(): void
    {
        $path = 'lessons/private-test.pdf';
        $contents = "%PDF-1.7 test document";
        $lesson = $this->lessonWithAttachment($path);
        Storage::disk('public')->put($path, $contents);

        $this->migration()->up();

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame($contents, Storage::disk('local')->get($path));
        $this->assertSame($path, $lesson->fresh()->attachment);
    }

    public function test_up_fails_without_losing_data_when_both_copies_are_missing(): void
    {
        $path = 'lessons/missing-test.pdf';
        $this->lessonWithAttachment($path);

        try {
            $this->migration()->up();
            $this->fail('Expected migration to stop when a referenced learning file is missing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('missing from both disks', $exception->getMessage());
        }
    }

    public function test_down_restores_public_copy_and_keeps_private_backup(): void
    {
        $path = 'lessons/rollback-test.pdf';
        $contents = "%PDF-1.7 rollback document";
        $this->lessonWithAttachment($path);
        Storage::disk('local')->put($path, $contents);

        $this->migration()->down();

        Storage::disk('public')->assertExists($path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame($contents, Storage::disk('public')->get($path));
        $this->assertSame($contents, Storage::disk('local')->get($path));
    }

    public function test_up_retains_public_file_when_existing_private_copy_differs(): void
    {
        $path = 'lessons/mismatch-test.pdf';
        $this->lessonWithAttachment($path);
        Storage::disk('local')->put($path, 'private version');
        Storage::disk('public')->put($path, 'public version');

        try {
            $this->migration()->up();
            $this->fail('Expected migration to stop on a checksum mismatch.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('verification failed', $exception->getMessage());
        }

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertExists($path);
    }
}
