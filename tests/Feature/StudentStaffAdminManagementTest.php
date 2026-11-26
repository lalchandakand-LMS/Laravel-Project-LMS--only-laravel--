<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentStaffAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_a_student_account_and_academic_profile_together(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Student One',
            'email' => 'student.one@example.com',
            'phone' => '01700000000',
            'role' => 'student',
            'password' => 'StrongPassword123!',
            'is_active' => '1',
            'student_code' => 'AEC-STU-1001',
            'school_name' => 'Alom Model School',
            'academic_class' => 'Class 8',
            'academic_roll' => '08',
            'academic_session' => '2026',
            'student_status' => 'active',
        ])->assertRedirect();

        $student = User::where('email', 'student.one@example.com')->firstOrFail();
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'student_code' => 'AEC-STU-1001',
            'academic_class' => 'Class 8',
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $student), [
            'name' => 'Student Updated',
            'email' => 'student.one@example.com',
            'phone' => '01700000001',
            'role' => 'student',
            'is_active' => '1',
            'student_code' => 'AEC-STU-1001',
            'school_name' => 'Updated School',
            'academic_class' => 'Class 9',
            'academic_roll' => '09',
            'academic_session' => '2027',
            'student_status' => 'active',
        ])->assertRedirect();

        $this->assertSame('Student Updated', $student->fresh()->name);
        $this->assertSame('Class 9', $student->fresh()->studentProfile->academic_class);
        $this->assertSame('2027', $student->fresh()->studentProfile->academic_session);
    }

    public function test_admin_can_create_staff_account_with_employment_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Teacher One',
            'email' => 'teacher.one@example.com',
            'phone' => '01700000002',
            'role' => 'instructor',
            'password' => 'StrongPassword123!',
            'is_active' => '1',
            'employee_code' => 'AEC-EMP-1001',
            'department' => 'English',
            'staff_designation' => 'Senior Teacher',
            'employment_type' => 'full_time',
            'joining_date' => '2024-01-10',
            'experience_years' => 8,
            'highest_qualification' => 'MA English',
            'institution' => 'National University',
            'employment_status' => 'active',
        ])->assertRedirect();

        $teacher = User::where('email', 'teacher.one@example.com')->firstOrFail();
        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $teacher->id,
            'employee_code' => 'AEC-EMP-1001',
            'department' => 'English',
            'designation' => 'Senior Teacher',
        ]);
        $this->assertInstanceOf(StaffProfile::class, $teacher->fresh()->staffProfile);
    }

    public function test_student_profile_fields_cannot_be_submitted_for_a_teacher_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Invalid Mixed Role',
                'email' => 'mixed@example.com',
                'role' => 'instructor',
                'password' => 'StrongPassword123!',
                'employee_code' => 'AEC-EMP-1002',
                'employment_status' => 'active',
                'student_code' => 'AEC-STU-1002',
            ])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors('student_code');

        $this->assertDatabaseMissing('users', ['email' => 'mixed@example.com']);
    }

    public function test_student_profile_page_displays_academic_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student']);
        $student->studentProfile()->create([
            'student_code' => 'AEC-STU-1003',
            'school_name' => 'Alom Model School',
            'academic_class' => 'Class 7',
            'academic_roll' => '11',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee('Student academic profile')
            ->assertSee('AEC-STU-1003')
            ->assertSee('Class 7');
    }
}
