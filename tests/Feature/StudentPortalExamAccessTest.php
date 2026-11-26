<?php

namespace Tests\\Feature;

use App\\Models\\Batch;
use App\\Models\\Branch;
use App\\Models\\Course;
use App\\Models\\Enrollment;
use App\\Models\\Exam;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class StudentPortalExamAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createPaidExam(): array
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Exam Access Course',
            'slug' => 'exam-access-course',
            'price' => 1200,
            'is_published' => true,
        ]);
        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => 'Course Final Exam',
            'duration_minutes' => 30,
            'total_marks' => 20,
            'pass_marks' => 10,
            'negative_marking' => 0,
            'is_free' => false,
            'is_published' => true,
        ]);

        return [$student, $course, $exam];
    }

    public function test_student_without_enrollment_cannot_view_or_start_paid_exam(): void
    {
        [$student, , $exam] = $this->createPaidExam();

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();
        $this->assertDatabaseMissing('exam_attempts', [
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_expired_enrollment_cannot_view_or_start_paid_exam(): void
    {
        [$student, $course, $exam] = $this->createPaidExam();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1200,
            'enrolled_at' => now()->subDays(40),
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();
    }

    public function test_ineligible_batch_or_branch_blocks_paid_exam(): void
    {
        [$student, $course, $exam] = $this->createPaidExam();
        $assignedBranch = Branch::create(['name' => 'Exam Branch', 'code' => 'EXAM-BR', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Other Exam Branch', 'code' => 'EXAM-OTHER', 'is_active' => true]);
        $student->update(['branch_id' => $otherBranch->id]);

        $batch = Batch::create([
            'course_id' => $course->id,
            'branch_id' => $assignedBranch->id,
            'name' => 'Active Batch',
            'code' => 'EXAM-ACTIVE',
            'status' => 'ongoing',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
            'price_paid' => 1200,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();

        $student->update(['branch_id' => $assignedBranch->id]);
        $batch->update(['status' => 'upcoming']);
        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
    }

    public function test_eligible_student_can_view_and_start_paid_exam(): void
    {
        [$student, $course, $exam] = $this->createPaidExam();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1200,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(10),
        ]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertOk();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();

        $this->assertDatabaseHas('exam_attempts', [
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_free_exam_is_available_without_course_enrollment(): void
    {
        [$student, , $exam] = $this->createPaidExam();
        $exam->update(['is_free' => true]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertOk();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
    }

    public function test_unpublished_course_exam_is_not_visible_even_when_marked_free(): void
    {
        [$student, $course, $exam] = $this->createPaidExam();
        $course->update(['is_published' => false]);
        $exam->update(['is_free' => true]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();
    }
}
