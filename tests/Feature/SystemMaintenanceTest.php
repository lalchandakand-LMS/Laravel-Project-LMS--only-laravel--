<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_open_activity_log_and_backup_center(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('Audit trail');

        $this->actingAs($admin)
            ->get(route('admin.system.backups.index'))
            ->assertOk()
            ->assertSee('Create database backup')
            ->assertSee('Restore database backup');
    }

    public function test_non_admin_cannot_access_audit_or_backup_management(): void
    {
        $student = User::where('email', 'student@lms.test')->firstOrFail();

        $this->actingAs($student)
            ->get(route('admin.activity-logs.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.system.backups.index'))
            ->assertForbidden();
    }

    public function test_logout_action_is_written_to_activity_audit(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('logout'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'route' => 'logout',
            'method' => 'POST',
            'event' => 'post',
        ]);
    }

    public function test_deleted_user_is_soft_deleted_and_records_actor(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $this->actingAs($admin);

        $student = User::create([
            'name' => 'Recycle Bin Student',
            'email' => 'recycle-student@example.test',
            'password' => 'password',
            'role' => 'student',
        ]);

        $student->delete();

        $deletedStudent = User::withTrashed()->findOrFail($student->id);
        $this->assertNotNull($deletedStudent->deleted_at);
        $this->assertSame($admin->id, (int) $deletedStudent->deleted_by);
        $this->assertTrue(User::onlyTrashed()->whereKey($student->id)->exists());
    }

    public function test_sqlite_restore_rejects_a_database_missing_required_lms_tables_before_replacement(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'aec-invalid-backup-');
        $target = tempnam(sys_get_temp_dir(), 'aec-live-database-');
        $this->assertNotFalse($source);
        $this->assertNotFalse($target);

        try {
            new \\PDO('sqlite:'.$source);
            $targetBefore = file_get_contents($target);
            $controller = app(\\App\\Http\\Controllers\\Admin\\SystemBackupController::class);
            $method = new \\ReflectionMethod($controller, 'replaceSqliteDatabase');

            try {
                $method->invoke($controller, $source, $target);
                $this->fail('An SQLite database without the LMS schema must not be restored.');
            } catch (\\RuntimeException $exception) {
                $this->assertStringContainsString('missing required LMS table', $exception->getMessage());
            }

            $this->assertSame($targetBefore, file_get_contents($target), 'The existing database file must remain untouched.');
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

}
