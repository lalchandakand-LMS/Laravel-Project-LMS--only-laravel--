<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_and_update_daily_staff_attendance(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'instructor', 'branch_id' => $branch->id, 'is_active' => true]);
        $teacher->staffProfile()->create(['employee_code' => 'AEC-ATT-001', 'employment_status' => 'active']);

        $this->actingAs($admin)->post(route('admin.staff-attendance.save'), [
            'attendance_date' => '2026-11-26',
            'records' => [[
                'user_id' => $teacher->id,
                'status' => 'present',
                'check_in' => '08:55',
                'check_out' => '16:05',
                'notes' => 'On time.',
            ]],
        ])->assertRedirect(route('admin.staff-attendance.index', ['date' => '2026-11-26']));

        $this->assertDatabaseHas('staff_attendances', [
            'branch_id' => $branch->id,
            'attendance_date' => '2026-11-26',
            'status' => 'present',
            'check_in' => '08:55',
            'marked_by' => $admin->id,
        ]);

        $this->post(route('admin.staff-attendance.save'), [
            'attendance_date' => '2026-11-26',
            'records' => [[
                'user_id' => $teacher->id,
                'status' => 'late',
                'check_in' => '09:30',
                'check_out' => '16:00',
            ]],
        ])->assertRedirect();

        $this->assertSame(1, StaffAttendance::count());
        $this->assertSame('late', StaffAttendance::first()->status);
    }

    public function test_non_admin_cannot_manage_staff_attendance(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $this->actingAs($teacher)->get(route('admin.staff-attendance.index'))->assertForbidden();
    }

    public function test_attendance_rejects_invalid_staff_role_and_checkout_before_checkin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->from(route('admin.staff-attendance.index'))
            ->post(route('admin.staff-attendance.save'), [
                'attendance_date' => '2026-11-26',
                'records' => [[
                    'user_id' => $student->id,
                    'status' => 'present',
                    'check_in' => '10:00',
                    'check_out' => '09:00',
                ]],
            ])->assertRedirect(route('admin.staff-attendance.index'))
            ->assertSessionHasErrors();
    }
}
