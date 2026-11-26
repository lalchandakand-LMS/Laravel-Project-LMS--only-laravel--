<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function assignmentFixture(array $overrides = []): array
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Assignment Course',
            'slug' => 'assignment-course',
            'price' => 1000,
            'is_published' => true,
            'instructor_id' => $teacher->id,
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1000,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $assignment = Assignment::create(array_merge([
            'course_id' => $course->id,
            'title' => 'Write a short essay',
            'description' => 'Practice your writing.',
            'instructions' => 'Write 200 words about your favourite book.',
            'total_marks' => 100,
            'is_published' => true,
        ], $overrides));

        return [$teacher, $student, $course, $enrollment, $assignment];
    }

    public function test_student_only_sees_published_assignments_for_active_published_courses(): void
    {
        [$teacher, $student, $course] = $this->assignmentFixture();
        Assignment::create([
            'course_id' => $course->id,
            'title' => 'Draft Assignment',
            'is_published' => false,
            'total_marks' => 100,
        ]);

        $otherCourse = Course::create([
            'title' => 'Not Enrolled Course',
            'slug' => 'not-enrolled-assignment-course',
            'price' => 1000,
            'is_published' => true,
            'instructor_id' => $teacher->id,
        ]);
        Assignment::create([
            'course_id' => $otherCourse->id,
            'title' => 'Other Course Assignment',
            'is_published' => true,
            'total_marks' => 100,
        ]);

        $this->actingAs($student)
            ->get(route('student.assignments.index'))
            ->assertOk()
            ->assertSee('Write a short essay')
            ->assertDontSee('Draft Assignment')
            ->assertDontSee('Other Course Assignment');
    }

    public function test_expired_enrollment_cannot_view_or_submit_assignment(): void
    {
        [, $student, , $enrollment, $assignment] = $this->assignmentFixture();
        $enrollment->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($student)
            ->get(route('student.assignments.show', $assignment))
            ->assertNotFound();

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), ['answer_text' => 'My work'])
            ->assertNotFound();

        $this->assertDatabaseMissing('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_student_submission_cannot_be_repeated_until_teacher_returns_it(): void
    {
        [$teacher, $student, , , $assignment] = $this->assignmentFixture();

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), ['answer_text' => 'First version'])
            ->assertRedirect(route('student.assignments.show', $assignment));

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('submitted', $submission->status);

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), ['answer_text' => 'Second version'])
            ->assertStatus(409);

        $this->actingAs($teacher)
            ->post(route('teacher.assignments.grade', [$assignment, $submission]), [
                'status' => 'returned',
                'feedback' => 'Please add more examples.',
            ])
            ->assertRedirect();

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), ['answer_text' => 'Revised version'])
            ->assertRedirect(route('student.assignments.show', $assignment));

        $submission->refresh();
        $this->assertSame('submitted', $submission->status);
        $this->assertSame('Revised version', $submission->answer_text);
        $this->assertNull($submission->feedback);
    }

    public function test_teacher_can_grade_submission_but_cannot_access_another_teachers_assignment(): void
    {
        [$teacher, $student, , , $assignment] = $this->assignmentFixture();
        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer_text' => 'A complete answer',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.assignments.grade', [$assignment, $submission]), [
                'status' => 'graded',
                'score' => 85,
                'feedback' => 'Well done.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'status' => 'graded',
            'score' => 85,
            'graded_by' => $teacher->id,
        ]);

        $otherTeacher = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        $this->actingAs($otherTeacher)
            ->get(route('teacher.assignments.submissions', $assignment))
            ->assertForbidden();
    }

    public function test_assignment_deadline_prevents_late_submission(): void
    {
        [, $student, , , $assignment] = $this->assignmentFixture([
            'due_at' => now()->subMinute(),
        ]);

        $this->actingAs($student)
            ->get(route('student.assignments.show', $assignment))
            ->assertOk()
            ->assertSee('The submission deadline has passed');

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), ['answer_text' => 'Late work'])
            ->assertForbidden();
    }

    public function test_submission_files_are_stored_privately_and_downloaded_only_by_the_owner_or_teacher(): void
    {
        Storage::fake('local');
        [$teacher, $student, , , $assignment] = $this->assignmentFixture();

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), [
                'attachment' => UploadedFile::fake()->create('homework.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect();

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)->firstOrFail();
        Storage::disk('local')->assertExists($submission->attachment);

        $this->actingAs($student)
            ->get(route('student.assignments.submission-file', $assignment))
            ->assertOk();

        $otherStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($otherStudent)
            ->get(route('student.assignments.submission-file', $assignment))
            ->assertNotFound();

        $this->actingAs($teacher)
            ->get(route('teacher.assignments.submission-file', [$assignment, $submission]))
            ->assertOk();
    }

    public function test_returned_submission_replaces_its_private_file_after_the_new_file_is_stored(): void
    {
        Storage::fake('local');
        [$teacher, $student, , , $assignment] = $this->assignmentFixture();

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'attachment' => UploadedFile::fake()->create('first-homework.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)->firstOrFail();
        $oldAttachment = $submission->attachment;
        Storage::disk('local')->assertExists($oldAttachment);

        $this->actingAs($teacher)->post(route('teacher.assignments.grade', [$assignment, $submission]), [
            'status' => 'returned',
            'feedback' => 'Please revise and resubmit.',
        ])->assertRedirect();

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'answer_text' => 'Revised work',
            'attachment' => UploadedFile::fake()->create('revised-homework.pdf', 55, 'application/pdf'),
        ])->assertRedirect();

        $submission->refresh();
        $this->assertNotSame($oldAttachment, $submission->attachment);
        Storage::disk('local')->assertMissing($oldAttachment);
        Storage::disk('local')->assertExists($submission->attachment);
    }


    public function test_failed_resubmission_keeps_the_previous_private_file_and_cleans_the_new_upload(): void
    {
        Storage::fake('local');
        [, $student, , , $assignment] = $this->assignmentFixture();
        $oldAttachment = 'assignments/submissions/original-homework.pdf';
        Storage::disk('local')->put($oldAttachment, 'original-private-file');

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer_text' => 'Please review this version',
            'attachment' => $oldAttachment,
            'submitted_at' => now(),
            'status' => 'returned',
        ]);

        AssignmentSubmission::updating(function () {
            throw new \\RuntimeException('Simulated database write failure.');
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
                'answer_text' => 'Updated work',
                'attachment' => UploadedFile::fake()->create('replacement.pdf', 60, 'application/pdf'),
            ]);
            $this->fail('The simulated submission update failure should be rethrown.');
        } catch (\\RuntimeException $exception) {
            $this->assertSame('Simulated database write failure.', $exception->getMessage());
        }

        $submission->refresh();
        $this->assertSame($oldAttachment, $submission->attachment);
        Storage::disk('local')->assertExists($oldAttachment);
        $this->assertSame([], Storage::disk('local')->allFiles('assignments/submissions') === [$oldAttachment]
            ? [] : array_values(array_diff(Storage::disk('local')->allFiles('assignments/submissions'), [$oldAttachment])));
    }

}
