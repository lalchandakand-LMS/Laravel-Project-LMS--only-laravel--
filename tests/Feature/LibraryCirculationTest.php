<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryCirculationTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $overrides = []): LibraryBook
    {
        return LibraryBook::create(array_merge([
            'title' => 'English Grammar in Use',
            'author' => 'Raymond Murphy',
            'category' => 'English',
            'total_copies' => 2,
            'available_copies' => 2,
            'is_active' => true,
        ], $overrides));
    }

    public function test_student_catalogue_is_scoped_to_global_and_own_branch_books(): void
    {
        $studentBranch = Branch::create(['name' => 'Library Branch A', 'code' => 'LIB-A', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Library Branch B', 'code' => 'LIB-B', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $studentBranch->id]);

        $this->book(['title' => 'Global English Book']);
        $this->book(['title' => 'Branch A Book', 'branch_id' => $studentBranch->id]);
        $this->book(['title' => 'Branch B Book', 'branch_id' => $otherBranch->id]);

        $this->actingAs($student)
            ->get(route('student.library.index'))
            ->assertOk()
            ->assertSee('Global English Book')
            ->assertSee('Branch A Book')
            ->assertDontSee('Branch B Book');
    }

    public function test_student_can_request_and_cancel_a_book_but_cannot_duplicate_active_requests(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $book = $this->book();

        $this->actingAs($student)
            ->post(route('student.library.request', $book))
            ->assertRedirect(route('student.library.index'));

        $loan = LibraryLoan::firstOrFail();
        $this->assertSame('requested', $loan->status);

        $this->actingAs($student)
            ->post(route('student.library.request', $book))
            ->assertStatus(409);

        $this->actingAs($student)
            ->post(route('student.library.cancel', $loan))
            ->assertRedirect();

        $this->assertSame('cancelled', $loan->fresh()->status);
    }

    public function test_admin_issue_decreases_stock_and_return_restores_stock_with_fine_record(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $book = $this->book(['total_copies' => 2, 'available_copies' => 2]);
        $loan = LibraryLoan::create([
            'book_id' => $book->id,
            'student_id' => $student->id,
            'requested_at' => now(),
            'status' => 'requested',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.loans.issue', $loan), [
                'due_at' => now()->addDays(14)->format('Y-m-d H:i:s'),
                'notes' => 'Issued in good condition.',
            ])
            ->assertRedirect();

        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertDatabaseHas('library_loans', [
            'id' => $loan->id,
            'status' => 'issued',
            'issued_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.loans.return', $loan), [
                'fine_amount' => 25,
                'return_condition' => 'Returned with a damaged cover.',
            ])
            ->assertRedirect();

        $this->assertSame(2, $book->fresh()->available_copies);
        $this->assertDatabaseHas('library_loans', [
            'id' => $loan->id,
            'status' => 'returned',
            'fine_amount' => 25,
            'return_condition' => 'Returned with a damaged cover.',
        ]);
    }

    public function test_admin_cannot_issue_when_stock_is_empty_or_book_is_for_another_branch(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentBranch = Branch::create(['name' => 'Student Library Branch', 'code' => 'LIB-ST', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Other Library Branch', 'code' => 'LIB-OT', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true, 'branch_id' => $studentBranch->id]);
        $book = $this->book(['branch_id' => $otherBranch->id, 'total_copies' => 1, 'available_copies' => 1]);
        $loan = LibraryLoan::create([
            'book_id' => $book->id,
            'student_id' => $student->id,
            'requested_at' => now(),
            'status' => 'requested',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.loans.issue', $loan), [
                'due_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();

        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertSame('requested', $loan->fresh()->status);
    }

    public function test_student_cannot_access_admin_library_management(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)
            ->get(route('admin.library.books.index'))
            ->assertForbidden();
    }
}
