<?php

namespace Tests\Feature;

use App\Models\StaffPayroll;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPayrollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_approve_and_record_a_staff_payroll_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $teacher->staffProfile()->create(['employee_code' => 'AEC-PAY-001', 'employment_status' => 'active']);

        $this->actingAs($admin)->post(route('admin.staff-payroll.store'), [
            'staff_user_id' => $teacher->id,
            'payroll_month' => '2026-11',
            'basic_salary' => 30000,
            'allowances' => 5000,
            'deductions' => 2000,
            'notes' => 'November payroll',
        ])->assertRedirect();

        $payroll = StaffPayroll::firstOrFail();
        $this->assertSame('draft', $payroll->status);
        $this->assertSame('33000.00', $payroll->net_salary);

        $this->actingAs($admin)->post(route('admin.staff-payroll.approve', $payroll))->assertRedirect();
        $this->assertSame('approved', $payroll->fresh()->status);

        $this->actingAs($admin)->post(route('admin.staff-payroll.paid', $payroll), [
            'paid_at' => '2026-11-26',
            'payment_method' => 'bank',
            'payment_reference' => 'BANK-REF-100',
        ])->assertRedirect();

        $this->assertDatabaseHas('staff_payrolls', [
            'id' => $payroll->id,
            'status' => 'paid',
            'net_salary' => 33000,
            'paid_by' => $admin->id,
            'payment_reference' => 'BANK-REF-100',
        ]);
    }

    public function test_payroll_rejects_excess_deductions_and_duplicate_staff_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $teacher->staffProfile()->create(['employment_status' => 'active']);

        $payload = [
            'staff_user_id' => $teacher->id,
            'payroll_month' => '2026-11',
            'basic_salary' => 10000,
            'allowances' => 1000,
            'deductions' => 12000,
        ];

        $this->actingAs($admin)->from(route('admin.staff-payroll.index'))
            ->post(route('admin.staff-payroll.store'), $payload)
            ->assertRedirect(route('admin.staff-payroll.index'))
            ->assertSessionHasErrors();

        $payload['deductions'] = 500;
        $this->post(route('admin.staff-payroll.store'), $payload)->assertRedirect();
        $this->post(route('admin.staff-payroll.store'), $payload)->assertSessionHasErrors();

        $this->assertDatabaseCount('staff_payrolls', 1);
    }

    public function test_only_approved_payroll_can_be_paid_and_paid_records_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'instructor']);
        $profile = $teacher->staffProfile()->create(['employment_status' => 'active']);
        $payroll = StaffPayroll::create([
            'staff_profile_id' => $profile->id,
            'payroll_month' => '2026-10',
            'basic_salary' => 20000,
            'allowances' => 0,
            'deductions' => 0,
            'net_salary' => 20000,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->post(route('admin.staff-payroll.paid', $payroll), [
            'paid_at' => '2026-11-01',
            'payment_method' => 'cash',
        ])->assertStatus(409);

        $payroll->update(['status' => 'paid']);
        $this->delete(route('admin.staff-payroll.destroy', $payroll))->assertStatus(409);
        $this->assertDatabaseHas('staff_payrolls', ['id' => $payroll->id, 'status' => 'paid']);
    }

    public function test_approved_payroll_cannot_be_approved_or_deleted_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $profile = $teacher->staffProfile()->create(['employment_status' => 'active']);
        $payroll = StaffPayroll::create([
            'staff_profile_id' => $profile->id,
            'payroll_month' => '2026-12',
            'basic_salary' => 20000,
            'allowances' => 0,
            'deductions' => 0,
            'net_salary' => 20000,
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->post(route('admin.staff-payroll.approve', $payroll))
            ->assertStatus(409);
        $this->delete(route('admin.staff-payroll.destroy', $payroll))
            ->assertStatus(409);

        $this->assertDatabaseHas('staff_payrolls', [
            'id' => $payroll->id,
            'status' => 'approved',
        ]);
    }

    public function test_only_active_staff_can_receive_payroll_and_concurrent_month_duplicate_is_reported(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $inactive = User::factory()->create(['role' => 'instructor', 'is_active' => false]);
        $inactive->staffProfile()->create(['employment_status' => 'active']);

        $this->actingAs($admin)->from(route('admin.staff-payroll.index'))
            ->post(route('admin.staff-payroll.store'), [
                'staff_user_id' => $inactive->id,
                'payroll_month' => '2026-12',
                'basic_salary' => 20000,
            ])->assertRedirect(route('admin.staff-payroll.index'))
                ->assertSessionHasErrors();

        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $profile = $teacher->staffProfile()->create(['employment_status' => 'active']);
        StaffPayroll::create([
            'staff_profile_id' => $profile->id,
            'payroll_month' => '2026-12',
            'basic_salary' => 20000,
            'allowances' => 0,
            'deductions' => 0,
            'net_salary' => 20000,
            'status' => 'draft',
        ]);

        $this->post(route('admin.staff-payroll.store'), [
            'staff_user_id' => $teacher->id,
            'payroll_month' => '2026-12',
            'basic_salary' => 21000,
        ])->assertRedirect()->assertSessionHasErrors('payroll_month');

        $this->assertSame(1, StaffPayroll::where('staff_profile_id', $profile->id)->where('payroll_month', '2026-12')->count());
    }

    public function test_non_admin_cannot_open_payroll_management(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $this->actingAs($teacher)->get(route('admin.staff-payroll.index'))->assertForbidden();
    }
}
