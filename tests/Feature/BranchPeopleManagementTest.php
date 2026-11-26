<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchPeopleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function setupBranchManager(): array
    {
        $branch = Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id, 'is_active' => true]);

        return [$branch, $manager];
    }

    public function test_branch_manager_can_create_and_edit_student_and_staff_only_in_their_branch(): void
    {
        [$branch, $manager] = $this->setupBranchManager();

        $this->actingAs($manager)->post(route('branch.people.store', 'student'), [
            'name' => 'Branch Student',
            'email' => 'branch.student@example.com',
            'phone' => '01700000021',
            'password' => 'StrongPassword123!',
            'student_code' => 'DHK-STU-001',
            'school_name' => 'Dhaka School',
            'academic_class' => 'Class 6',
            'academic_roll' => '21',
            'academic_session' => '2026',
        ])->assertRedirect(route('branch.students'));

        $student = User::where('email', 'branch.student@example.com')->firstOrFail();
        $this->assertSame($branch->id, $student->branch_id);
        $this->assertSame('DHK-STU-001', $student->studentProfile->student_code);

        $this->actingAs($manager)->put(route('branch.people.update', $student), [
            'name' => 'Updated Branch Student',
            'email' => 'branch.student@example.com',
            'phone' => '01700000022',
            'school_name' => 'Updated School',
            'academic_class' => 'Class 7',
            'academic_roll' => '22',
            'academic_session' => '2027',
            'student_status' => 'active',
            'is_active' => '1',
        ])->assertRedirect(route('branch.students'));

        $this->assertSame('Updated Branch Student', $student->fresh()->name);
        $this->assertSame('Class 7', $student->fresh()->studentProfile->academic_class);

        $this->post(route('branch.people.store', 'staff'), [
            'name' => 'Branch Teacher',
            'email' => 'branch.teacher@example.com',
            'phone' => '01700000023',
            'password' => 'StrongPassword123!',
            'department' => 'English',
            'staff_designation' => 'Teacher',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ])->assertRedirect(route('branch.staff'));

        $teacher = User::where('email', 'branch.teacher@example.com')->firstOrFail();
        $this->assertSame($branch->id, $teacher->branch_id);
        $this->assertSame('English', $teacher->staffProfile->department);
    }

    public function test_branch_manager_cannot_edit_or_delete_people_from_another_branch(): void
    {
        [$branch, $manager] = $this->setupBranchManager();
        $otherBranch = Branch::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
        $otherStudent = User::factory()->create(['role' => 'student', 'branch_id' => $otherBranch->id]);

        $this->actingAs($manager)->get(route('branch.people.edit', $otherStudent))->assertNotFound();
        $this->delete(route('branch.people.destroy', $otherStudent))->assertNotFound();
    }

    public function test_branch_manager_cannot_assign_another_branch_or_privileged_role(): void
    {
        [$branch, $manager] = $this->setupBranchManager();
        $otherBranch = Branch::create(['name' => 'Sylhet', 'code' => 'SYL', 'is_active' => true]);

        $this->actingAs($manager)->post(route('branch.people.store', 'staff'), [
            'name' => 'Attempted Escalation',
            'email' => 'escalation@example.com',
            'password' => 'StrongPassword123!',
            'role' => 'admin',
            'branch_id' => $otherBranch->id,
        ])->assertSessionHasErrors();

        $this->assertDatabaseMissing('users', ['email' => 'escalation@example.com']);
    }
}
