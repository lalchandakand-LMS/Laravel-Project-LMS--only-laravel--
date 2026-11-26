<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student', 'is_active' => true]);
    }

    private function course(): Course
    {
        return Course::create(['title' => 'Manual English', 'price' => 1500, 'is_published' => true]);
    }

    public function test_admin_can_manually_enroll_a_student_and_audit_the_creation(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $course = $this->course();

        $this->actingAs($admin)->post(route('admin.enrollments.store'), [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'price_paid' => 0,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
            'admin_note' => 'Scholarship access',
        ])->assertRedirect(route('admin.enrollments.index'));

        $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->firstOrFail();
        $this->assertSame('active', $enrollment->status);
        $this->assertSame('Scholarship access', $enrollment->admin_note);
        $this->assertDatabaseHas('enrollment_histories', [
            'enrollment_id' => $enrollment->id,
            'actor_id' => $admin->id,
            'action' => 'manually_created',
        ]);
    }

    public function test_manual_enrollment_rejects_duplicate_active_access_and_non_student_accounts(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $course = $this->course();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'price_paid' => 1500, 'status' => 'active', 'enrolled_at' => now()]);

        $payload = ['user_id' => $student->id, 'course_id' => $course->id, 'price_paid' => 0, 'status' => 'active', 'enrolled_at' => now()->toDateString()];
        $this->actingAs($admin)->from(route('admin.enrollments.index'))
            ->post(route('admin.enrollments.store'), $payload)
            ->assertRedirect(route('admin.enrollments.index'))
            ->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('enrollments', 1);

        $teacher = User::factory()->create(['role' => 'instructor']);
        $this->actingAs($admin)->post(route('admin.enrollments.store'), array_merge($payload, ['user_id' => $teacher->id]))
            ->assertStatus(422);
    }

    public function test_admin_can_edit_batch_status_and_expiry_with_audit_history(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $course = $this->course();
        $batch = Batch::create(['course_id' => $course->id, 'name' => 'Evening Batch', 'code' => 'ENR-EVE', 'status' => 'ongoing']);
        $enrollment = Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'price_paid' => 500, 'status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($admin)->put(route('admin.enrollments.update', $enrollment), [
            'batch_id' => $batch->id,
            'price_paid' => 750,
            'status' => 'suspended',
            'enrolled_at' => now()->subDay()->toDateString(),
            'expires_at' => now()->addMonth()->toDateString(),
            'admin_note' => 'Payment installment pending',
        ])->assertRedirect(route('admin.enrollments.show', $enrollment));

        $this->assertSame($batch->id, $enrollment->fresh()->batch_id);
        $this->assertSame('suspended', $enrollment->fresh()->status);
        $this->assertSame('750.00', $enrollment->fresh()->price_paid);
        $this->assertDatabaseHas('enrollment_histories', ['enrollment_id' => $enrollment->id, 'action' => 'updated']);
    }

    public function test_progress_is_calculated_from_completed_published_lessons(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $course = $this->course();
        $lessonOne = Lesson::create(['course_id' => $course->id, 'title' => 'Lesson one', 'is_published' => true]);
        Lesson::create(['course_id' => $course->id, 'title' => 'Lesson two', 'is_published' => true]);
        Lesson::create(['course_id' => $course->id, 'title' => 'Draft lesson', 'is_published' => false]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'price_paid' => 0, 'status' => 'active', 'enrolled_at' => now()]);
        LessonProgress::create(['user_id' => $student->id, 'lesson_id' => $lessonOne->id, 'watched_seconds' => 90, 'completed_at' => now()]);

        $this->actingAs($admin)->get(route('admin.enrollments.index'))
            ->assertOk()->assertSee('50%');
    }

    public function test_cancelling_enrollment_preserves_the_record_and_bulk_status_updates_are_audited(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $course = $this->course();
        $enrollment = Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'price_paid' => 1200, 'status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($admin)->delete(route('admin.enrollments.destroy', $enrollment))->assertRedirect();
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'cancelled', 'price_paid' => 1200]);

        $second = Enrollment::create(['user_id' => $this->student()->id, 'course_id' => $course->id, 'price_paid' => 0, 'status' => 'active', 'enrolled_at' => now()]);
        $this->actingAs($admin)->post(route('admin.enrollments.bulk-update'), [
            'enrollment_ids' => [$second->id],
            'status' => 'suspended',
        ])->assertRedirect();
        $this->assertSame('suspended', $second->fresh()->status);
        $this->assertDatabaseHas('enrollment_histories', ['enrollment_id' => $second->id, 'action' => 'bulk_status_changed']);
    }

    public function test_admin_can_export_filtered_enrollment_records_as_csv(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $course = $this->course();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'price_paid' => 100, 'status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.enrollments.export', ['status' => 'active']));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_manual_enrollment_rejects_a_full_batch(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $otherStudent = $this->student();
        $course = $this->course();
        $batch = Batch::create(['course_id' => $course->id, 'name' => 'Full Batch', 'code' => 'ENR-FULL', 'status' => 'ongoing', 'capacity' => 1]);
        Enrollment::create(['user_id' => $otherStudent->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'price_paid' => 0, 'status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($admin)->post(route('admin.enrollments.store'), [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'price_paid' => 0,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ])->assertStatus(422);

        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }
}
