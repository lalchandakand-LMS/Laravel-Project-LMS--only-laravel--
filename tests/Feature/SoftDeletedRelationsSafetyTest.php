<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\Course;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletedRelationsSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_certificate_verification_survives_soft_deleted_student_and_course(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create(['title' => 'Archived English Course', 'slug' => 'archived-english-course']);
        $certificate = Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'issued_at' => now(),
        ]);

        $student->delete();
        $course->delete();

        $this->get(route('certificates.verify', $certificate->code))
            ->assertOk()
            ->assertSee('Former student')
            ->assertSee('Course unavailable')
            ->assertDontSee("Redwan's Method");
    }

    public function test_student_certificate_list_handles_a_soft_deleted_course(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['title' => 'Archived Student Course', 'slug' => 'archived-student-course']);
        $certificate = Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'issued_at' => now(),
        ]);

        $course->delete();

        $this->actingAs($student)->get(route('student.certificates'))
            ->assertOk()
            ->assertSee('Course unavailable')
            ->assertSee($certificate->code)
            ->assertDontSee('archived-student-course');
    }

    public function test_complaint_views_handle_soft_deleted_complainant_and_reply_author(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $complainant = User::factory()->create(['role' => 'student']);
        $replyAuthor = User::factory()->create(['role' => 'instructor']);
        $complaint = Complaint::create([
            'tracking_code' => 'AEC-SOFT-DELETE-001',
            'user_id' => $complainant->id,
            'category' => 'technical',
            'subject' => 'Deleted account complaint',
            'body' => 'This complaint remains visible after the account is deleted.',
        ]);
        ComplaintReply::create([
            'complaint_id' => $complaint->id,
            'user_id' => $replyAuthor->id,
            'body' => 'This reply author will be soft deleted.',
            'is_internal' => false,
        ]);

        $complainant->delete();
        $replyAuthor->delete();

        $this->actingAs($admin)->get(route('admin.complaints.index'))
            ->assertOk()
            ->assertSee('Deleted user');

        $this->actingAs($admin)->get(route('admin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('Deleted user')
            ->assertSee('This reply author will be soft deleted.')
            ->assertSee('—');

        $this->actingAs($admin)->get(route('complaints.show', $complaint))
            ->assertOk()
            ->assertSee('Deleted user')
            ->assertSee('This reply author will be soft deleted.');
    }

    public function test_library_loan_issue_is_rejected_when_student_was_soft_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $book = LibraryBook::create([
            'title' => 'Soft Delete Safety Book',
            'author' => 'Test Author',
            'category' => 'English',
            'total_copies' => 1,
            'available_copies' => 1,
            'is_active' => true,
        ]);
        $loan = LibraryLoan::create([
            'book_id' => $book->id,
            'student_id' => $student->id,
            'requested_at' => now(),
            'status' => 'requested',
        ]);

        $student->delete();

        $this->actingAs($admin)->post(route('admin.library.loans.issue', $loan), [
            'due_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ])->assertStatus(409);

        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertSame('requested', $loan->fresh()->status);
    }
}
