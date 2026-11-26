<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentLiveClassAccessTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudentWithCourse(): array
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Live Class Course',
            'slug' => 'live-class-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$student, $course];
    }

    private function liveClass(Course $course, array $attributes = []): LiveClass
    {
        return LiveClass::create(array_merge([
            'course_id' => $course->id,
            'title' => 'Weekly Speaking Practice',
            'description' => 'Practice speaking with your teacher.',
            'platform' => 'Google Meet',
            'meeting_url' => 'https://meet.example.test/secret-room',
            'meeting_password' => 'private-pass-123',
            'starts_at' => now()->addHour(),
            'duration_minutes' => 60,
            'is_published' => true,
        ], $attributes));
    }

    public function test_student_only_sees_published_live_classes_for_published_enrolled_courses(): void
    {
        [$student, $course] = $this->enrolledStudentWithCourse();
        $this->liveClass($course);

        $this->liveClass($course, [
            'title' => 'Unpublished Session',
            'is_published' => false,
        ]);

        $otherCourse = Course::create([
            'title' => 'Not Enrolled Course',
            'slug' => 'not-enrolled-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        $this->liveClass($otherCourse, ['title' => 'Other Course Session']);

        $draftCourse = Course::create([
            'title' => 'Draft Course',
            'slug' => 'draft-course',
            'price' => 1000,
            'is_published' => false,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $draftCourse->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
        ]);
        $this->liveClass($draftCourse, ['title' => 'Draft Course Session']);

        $this->actingAs($student)
            ->get(route('student.live-classes'))
            ->assertOk()
            ->assertSee('Weekly Speaking Practice')
            ->assertDontSee('Unpublished Session')
            ->assertDontSee('Other Course Session')
            ->assertDontSee('Draft Course Session');
    }

    public function test_meeting_link_and_password_are_hidden_until_join_window(): void
    {
        [$student, $course] = $this->enrolledStudentWithCourse();
        $class = $this->liveClass($course, [
            'starts_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($student)
            ->get(route('student.live-classes'))
            ->assertOk()
            ->assertDontSee($class->meeting_url)
            ->assertDontSee($class->meeting_password)
            ->assertSee('join link unlocks 10 minutes before');

        $class->update(['starts_at' => now()->addMinutes(5)]);

        $this->actingAs($student)
            ->get(route('student.live-classes'))
            ->assertOk()
            ->assertSee($class->meeting_url)
            ->assertSee($class->meeting_password);
    }

    public function test_expired_or_batch_branch_ineligible_enrollments_do_not_expose_live_classes(): void
    {
        [$student, $course] = $this->enrolledStudentWithCourse();
        $class = $this->liveClass($course);

        Enrollment::where('user_id', $student->id)->where('course_id', $course->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($student)
            ->get(route('student.live-classes'))
            ->assertOk()
            ->assertDontSee($class->title);

        $branchA = Branch::create(['name' => 'Student Branch', 'code' => 'ST-BR', 'is_active' => true]);
        $branchB = Branch::create(['name' => 'Other Branch', 'code' => 'OT-BR', 'is_active' => true]);
        $student->update(['branch_id' => $branchA->id]);

        $batch = Batch::create([
            'course_id' => $course->id,
            'branch_id' => $branchB->id,
            'name' => 'Other branch batch',
            'code' => 'OTHER-BATCH',
            'status' => 'ongoing',
        ]);
        Enrollment::where('user_id', $student->id)->where('course_id', $course->id)
            ->update(['status' => 'active', 'expires_at' => null, 'batch_id' => $batch->id]);

        $this->actingAs($student)
            ->get(route('student.live-classes'))
            ->assertOk()
            ->assertDontSee($class->title);
    }

    public function test_recording_is_available_after_the_join_window_ends(): void
    {
        [$student, $course] = $this->enrolledStudentWithCourse();
        $class = $this->liveClass($course, [
            'title' => 'Past Recorded Session',
            'starts_at' => now()->subHours(2),
            'recording_url' => 'https://video.example.test/replay',
        ]);

        $this->actingAs($student)
            ->get(route('student.live-classes'))
            ->assertOk()
            ->assertSee('Past Recorded Session')
            ->assertSee($class->recording_url)
            ->assertDontSee($class->meeting_url)
            ->assertDontSee($class->meeting_password);
    }
}
