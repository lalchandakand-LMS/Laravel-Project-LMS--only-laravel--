<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SystemMediaRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $archiveDirectory;
    private string $backupDirectory;
    private string $destination;
    private array $safetyFilesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->archiveDirectory = storage_path('framework/testing/media-restore-'.uniqid());
        $this->backupDirectory = base_path('storage/backups');
        $this->destination = storage_path('app/public/media-restore-test.txt');
        $this->safetyFilesBefore = glob(storage_path('app/private/system-backups/aec-media-public-*.tar.gz')) ?: [];

        mkdir($this->archiveDirectory.'/source/public', 0750, true);
        if (! is_dir($this->backupDirectory)) {
            mkdir($this->backupDirectory, 0750, true);
        }
        if (! is_dir(dirname($this->destination))) {
            mkdir(dirname($this->destination), 0750, true);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->destination);
        foreach (array_diff(glob(storage_path('app/private/system-backups/aec-media-public-*.tar.gz')) ?: [], $this->safetyFilesBefore) as $file) {
            @unlink($file);
        }
        $this->removeDirectory($this->archiveDirectory);
        parent::tearDown();
    }

    public function test_admin_can_restore_a_valid_public_upload_archive(): void
    {
        file_put_contents($this->archiveDirectory.'/source/public/media-restore-test.txt', 'restored media');
        $name = 'uploads-'.now()->format('Ymd-His').'.tar.gz';
        $archive = $this->backupDirectory.'/'.$name;

        $tar = new Process(['tar', '-czf', $archive, '-C', $this->archiveDirectory.'/source', 'public']);
        $tar->run();
        $this->assertTrue($tar->isSuccessful(), $tar->getErrorOutput());

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.system.backups.media-restore'), [
                'name' => $name,
                'confirmation' => 'RESTORE MEDIA',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('restored media', file_get_contents($this->destination));
        @unlink($archive);
    }

    public function test_public_archive_cannot_contain_private_paths(): void
    {
        mkdir($this->archiveDirectory.'/source/private', 0750, true);
        file_put_contents($this->archiveDirectory.'/source/private/secret.txt', 'not public');
        $name = 'uploads-'.now()->format('Ymd-His').'.tar.gz';
        $archive = $this->backupDirectory.'/'.$name;

        $tar = new Process(['tar', '-czf', $archive, '-C', $this->archiveDirectory.'/source', 'private']);
        $tar->run();
        $this->assertTrue($tar->isSuccessful(), $tar->getErrorOutput());

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.system.backups.media-restore'), [
                'name' => $name,
                'confirmation' => 'RESTORE MEDIA',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFileDoesNotExist($this->destination);
        @unlink($archive);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
