<?php

namespace Tests\Feature;

use App\Models\StaffLeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_submit_leave_request_and_overlapping_request_is_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $start = today()->addDays(3)->toDateString();
        $end = today()->addDays(5)->toDateString();

        $this->actingAs($teacher)
            ->post(route('teacher.leave-requests.store'), [
                'start_date' => $start,
                'end_date' => $end,
                'leave_type' => 'annual',
                'reason' => 'Family commitment.',
            ])
            ->assertRedirect(route('teacher.leave-requests.index'));

        $this->assertDatabaseHas('staff_leave_requests', [
            'user_id' => $teacher->id,
            'status' => 'pending',
            'leave_type' => 'annual',
        ]);

        $this->actingAs($teacher)
            ->from(route('teacher.leave-requests.index'))
            ->post(route('teacher.leave-requests.store'), [
                'start_date' => today()->addDays(4)->toDateString(),
                'end_date' => today()->addDays(6)->toDateString(),
                'leave_type' => 'personal',
                'reason' => 'Overlapping dates.',
            ])
            ->assertRedirect(route('teacher.leave-requests.index'))
            ->assertSessionHasErrors('start_date');

        $this->assertSame(1, StaffLeaveRequest::where('user_id', $teacher->id)->count());
    }

    public function test_admin_can_approve_or_reject_pending_leave_requests(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $leave = StaffLeaveRequest::create([
            'user_id' => $teacher->id,
            'start_date' => today()->addDays(3),
            'end_date' => today()->addDays(4),
            'leave_type' => 'sick',
            'reason' => 'Medical leave.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.staff-leave-requests.approve', $leave), [
                'reviewer_note' => 'Approved.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_leave_requests', [
            'id' => $leave->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewer_note' => 'Approved.',
        ]);

        $otherLeave = StaffLeaveRequest::create([
            'user_id' => $teacher->id,
            'start_date' => today()->addDays(10),
            'end_date' => today()->addDays(11),
            'leave_type' => 'personal',
            'reason' => 'Personal appointment.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.staff-leave-requests.reject', $otherLeave), [
                'reviewer_note' => 'Insufficient coverage.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_leave_requests', [
            'id' => $otherLeave->id,
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_admin_cannot_approve_overlapping_approved_leave_periods(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $first = StaffLeaveRequest::create([
            'user_id' => $teacher->id,
            'start_date' => today()->addDays(5),
            'end_date' => today()->addDays(8),
            'leave_type' => 'annual',
            'reason' => 'First leave.',
            'status' => 'approved',
        ]);
        $second = StaffLeaveRequest::create([
            'user_id' => $teacher->id,
            'start_date' => today()->addDays(7),
            'end_date' => today()->addDays(9),
            'leave_type' => 'personal',
            'reason' => 'Second leave.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.staff-leave-requests.index'))
            ->post(route('admin.staff-leave-requests.approve', $second))
            ->assertRedirect(route('admin.staff-leave-requests.index'))
            ->assertSessionHasErrors('leave_request');

        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_teacher_can_cancel_only_their_own_pending_request(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $otherTeacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $leave = StaffLeaveRequest::create([
            'user_id' => $teacher->id,
            'start_date' => today()->addDays(2),
            'end_date' => today()->addDays(2),
            'leave_type' => 'emergency',
            'reason' => 'Urgent matter.',
            'status' => 'pending',
        ]);

        $this->actingAs($otherTeacher)
            ->post(route('teacher.leave-requests.cancel', $leave))
            ->assertNotFound();

        $this->actingAs($teacher)
            ->post(route('teacher.leave-requests.cancel', $leave))
            ->assertRedirect();

        $this->assertSame('cancelled', $leave->fresh()->status);
    }

    public function test_student_cannot_access_staff_leave_management(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)
            ->get(route('teacher.leave-requests.index'))
            ->assertForbidden();
    }
}
