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

        $this->assertNotNull($student->fresh()->deleted_at);
        $this->assertSame($admin->id, (int) $student->fresh()->deleted_by);
        $this->assertTrue(User::onlyTrashed()->whereKey($student->id)->exists());
    }
}
