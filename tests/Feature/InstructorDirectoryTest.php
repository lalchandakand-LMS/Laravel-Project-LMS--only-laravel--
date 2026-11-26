<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_save_public_instructor_profile_fields(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Physics Teacher',
            'email' => 'physics.teacher@example.com',
            'role' => 'instructor',
            'password' => 'StrongPassword123!',
            'is_active' => '1',
            'staff_designation' => 'Senior Physics Instructor',
            'employment_status' => 'active',
            'subject' => 'Physics',
            'panel_group' => 'Instructor Panel HSC',
            'education' => 'CSE, AUST',
            'youtube_url' => 'https://youtube.com/@physics',
            'facebook_url' => 'https://facebook.com/physics',
            'sort_order' => '3',
        ])->assertRedirect(route('admin.users.index'));

        $teacher = User::where('email', 'physics.teacher@example.com')->firstOrFail();

        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $teacher->id,
            'subject' => 'Physics',
            'panel_group' => 'Instructor Panel HSC',
            'education' => 'CSE, AUST',
            'youtube_url' => 'https://youtube.com/@physics',
            'facebook_url' => 'https://facebook.com/physics',
            'sort_order' => 3,
        ]);

        $this->actingAs($admin)->get(route('admin.users.edit', $teacher))
            ->assertOk()
            ->assertSee('Public instructor directory profile')
            ->assertSee('Instructor Panel HSC')
            ->assertSee('CSE, AUST');
    }

    public function test_instructor_directory_groups_and_sorts_active_teachers_and_links_to_profile(): void
    {
        $later = User::factory()->create([
            'name' => 'Teacher Later', 'role' => 'instructor', 'is_active' => true,
            'designation' => 'Physics Teacher', 'bio' => 'Physics educator.',
        ]);
        $later->staffProfile()->create([
            'subject' => 'Physics', 'panel_group' => 'HSC Panel', 'sort_order' => 20,
            'education' => 'MSc Physics', 'employment_status' => 'active',
        ]);

        $first = User::factory()->create([
            'name' => 'Teacher First', 'role' => 'instructor', 'is_active' => true,
            'designation' => 'English Teacher', 'bio' => 'English educator.',
        ]);
        $first->staffProfile()->create([
            'subject' => 'English', 'panel_group' => 'HSC Panel', 'sort_order' => 1,
            'education' => 'MA English', 'employment_status' => 'active',
        ]);

        $inactive = User::factory()->create(['name' => 'Inactive Teacher', 'role' => 'instructor', 'is_active' => false]);
        $inactive->staffProfile()->create(['panel_group' => 'HSC Panel', 'sort_order' => 0]);

        $this->get(route('instructors.index'))
            ->assertOk()
            ->assertSee('Our Expert Instructors')
            ->assertSee('HSC Panel')
            ->assertSeeInOrder(['Teacher First', 'Teacher Later'])
            ->assertDontSee('Inactive Teacher')
            ->assertSee(route('instructors.show', $first), false);
    }

    public function test_teacher_profile_shows_full_bio_and_only_published_courses(): void
    {
        $teacher = User::factory()->create([
            'name' => 'Course Teacher', 'role' => 'instructor', 'is_active' => true,
            'bio' => 'A detailed teacher biography.',
        ]);
        $teacher->staffProfile()->create([
            'professional_bio' => 'A full professional biography.',
            'subject' => 'Higher Math',
            'panel_group' => 'SSC Panel',
            'education' => '8 Years Experience',
            'employment_status' => 'active',
        ]);

        $published = Course::firstOrFail()->replicate();
        $published->forceFill([
            'title' => 'Publicly Published Teacher Course',
            'slug' => 'publicly-published-teacher-course',
            'instructor_id' => $teacher->id,
            'is_published' => true,
        ])->save();

        $draft = Course::firstOrFail()->replicate();
        $draft->forceFill([
            'title' => 'Private Draft Teacher Course',
            'slug' => 'private-draft-teacher-course',
            'instructor_id' => $teacher->id,
            'is_published' => false,
        ])->save();

        $this->get(route('instructors.show', $teacher))
            ->assertOk()
            ->assertSee('A full professional biography.')
            ->assertSee('Higher Math')
            ->assertSee('Publicly Published Teacher Course')
            ->assertDontSee('Private Draft Teacher Course');
        $this->get(route('teachers.public.show', $teacher))
            ->assertOk()
            ->assertSee('A full professional biography.');
    }

    public function test_inactive_or_non_teacher_accounts_cannot_be_opened_as_public_instructor_profiles(): void
    {
        $inactive = User::factory()->create(['role' => 'instructor', 'is_active' => false]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->get(route('instructors.show', $inactive))->assertNotFound();
        $this->get(route('instructors.show', $student))->assertNotFound();
    }

    public function test_admin_can_edit_public_instructor_page_heading_and_menu_contains_directory_link(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'instructors_title' => 'Meet our faculty',
            'instructors_subtitle' => 'Expert teachers for every learner.',
        ])->assertRedirect();

        $this->assertSame('Meet our faculty', Setting::get('instructors_title'));
        $this->assertSame('Expert teachers for every learner.', Setting::get('instructors_subtitle'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Instructors')
            ->assertSee('/instructors', false);

        $this->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Public instructors page heading')
            ->assertSee('Meet our faculty');
    }
}
