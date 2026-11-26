<?php

namespace Tests\\Feature;

use App\\Models\\StaffProfile;
use App\\Models\\StudentProfile;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class StudentStaffCoreProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_is_one_to_one_and_keeps_academic_information_separate_from_login_account(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $profile = $student->studentProfile()->create([
            'student_code' => 'AEC-STU-0001',
            'school_name' => 'Alom Model School',
            'academic_class' => 'Class 8',
            'academic_roll' => '018',
            'academic_session' => '2026',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(StudentProfile::class, $student->fresh()->studentProfile);
        $this->assertSame($student->id, $profile->user->id);
        $this->assertSame('018', $profile->academic_roll);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'student_code' => 'AEC-STU-0001',
        ]);
    }

    public function test_staff_profile_is_one_to_one_and_tracks_employment_information(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor']);

        $profile = $teacher->staffProfile()->create([
            'employee_code' => 'AEC-EMP-0001',
            'department' => 'English',
            'designation' => 'Senior Teacher',
            'employment_type' => 'full_time',
            'joining_date' => '2024-01-15',
            'highest_qualification' => 'MA English',
            'employment_status' => 'active',
        ]);

        $this->assertInstanceOf(StaffProfile::class, $teacher->fresh()->staffProfile);
        $this->assertSame($teacher->id, $profile->user->id);
        $this->assertSame('English', $profile->department);
        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $teacher->id,
            'employee_code' => 'AEC-EMP-0001',
        ]);
    }

    public function test_profile_records_are_removed_when_their_user_account_is_deleted(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'instructor']);
        $student->studentProfile()->create(['student_code' => 'AEC-STU-0002']);
        $teacher->staffProfile()->create(['employee_code' => 'AEC-EMP-0002']);

        $student->delete();
        $teacher->delete();

        $this->assertDatabaseMissing('student_profiles', ['user_id' => $student->id]);
        $this->assertDatabaseMissing('staff_profiles', ['user_id' => $teacher->id]);
    }
}
