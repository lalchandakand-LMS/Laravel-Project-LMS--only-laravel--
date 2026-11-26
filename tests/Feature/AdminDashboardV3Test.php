<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_requested_kpis_and_analytics_widgets(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total Students')
            ->assertSee('Total Teachers')
            ->assertSee('Total Courses')
            ->assertSee('Total Batches')
            ->assertSee('Analytics Overview')
            ->assertSee('Course Progress')
            ->assertSee('Live Class Now')
            ->assertSee('Recent Activities')
            ->assertSee('Upcoming Classes')
            ->assertSee('Top Students')
            ->assertSee('Quick Actions')
            ->assertSee('LMS v3.0');
    }

    public function test_admin_global_search_returns_students_and_courses_but_is_forbidden_to_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Searchable Learner', 'email' => 'searchable@example.com']);
        $course = Course::create([
            'title' => 'Searchable English Course',
            'slug' => 'searchable-english-course',
            'price' => 100,
            'is_published' => true,
        ]);

        $this->actingAs($admin)->getJson(route('admin.search', ['q' => 'Searchable']))
            ->assertOk()
            ->assertJsonFragment(['label' => $student->name, 'type' => 'Student'])
            ->assertJsonFragment(['label' => $course->title, 'type' => 'Course']);

        $this->actingAs($student)->getJson(route('admin.search', ['q' => 'Searchable']))
            ->assertForbidden();
    }

    public function test_admin_certificate_registry_lists_and_issues_only_for_completed_eligible_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Completed Certificate Course',
            'slug' => 'completed-certificate-course',
            'price' => 500,
            'is_published' => true,
            'has_certificate' => true,
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Completed lesson',
            'type' => 'text',
            'position' => 1,
            'is_published' => true,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 500,
            'enrolled_at' => now(),
        ]);
        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.certificates.index'))->assertOk()->assertSee('Generate Certificate');

        $this->actingAs($admin)->post(route('admin.certificates.store'), [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ])->assertRedirect(route('admin.certificates.index'));

        $certificate = Certificate::where('user_id', $student->id)->where('course_id', $course->id)->firstOrFail();
        $this->get(route('admin.certificates.index'))->assertOk()->assertSee($certificate->code)->assertSee('Verify certificate');
    }

    public function test_admin_cannot_issue_certificate_for_incomplete_course_or_student_cannot_open_registry(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Incomplete Certificate Course',
            'slug' => 'incomplete-certificate-course',
            'price' => 500,
            'is_published' => true,
            'has_certificate' => true,
        ]);
        Lesson::create([
            'course_id' => $course->id,
            'title' => 'Not completed lesson',
            'type' => 'text',
            'position' => 1,
            'is_published' => true,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 500,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.certificates.store'), [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('certificates', ['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($student)->get(route('admin.certificates.index'))->assertForbidden();
    }
    public function test_course_views_are_counted_once_per_course_per_session_per_day(): void
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $course = Course::create([
            'instructor_id' => $teacher->id,
            'title' => 'Daily View Tracking Course',
            'slug' => 'daily-view-tracking-course',
            'subtitle' => 'A course page view test',
            'description' => 'Course description for view tracking.',
            'price' => 100,
            'duration_minutes' => 60,
            'level' => 'Beginner',
            'language' => 'English',
            'is_published' => true,
        ]);

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->get(route('courses.show', $course->slug))->assertOk();
        $this->actingAs($student)->get(route('courses.show', $course->slug))->assertOk();

        $this->assertDatabaseCount('course_views', 1);
        $this->assertDatabaseHas('course_views', [
            'course_id' => $course->id,
            'viewed_on' => now()->toDateString(),
        ]);
    }

    public function test_admin_reports_center_is_available_only_to_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Academy Reports Center')
            ->assertSee('Detailed reports')
            ->assertSee('Payment & Revenue Reports')
            ->assertSee('Attendance Reports');

        $this->actingAs($student)->get(route('admin.reports.index'))->assertForbidden();
    }

}
