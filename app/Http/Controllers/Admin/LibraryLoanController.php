<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryLoanController extends Controller
{
    public function index(Request $request)
    {
        $loans = LibraryLoan::with(['book.branch', 'student', 'issuer'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->boolean('overdue'), fn ($query) => $query->where('status', 'issued')->where('due_at', '<', now()))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.library.loans.index', compact('loans'));
    }

    public function issue(Request $request, LibraryLoan $loan)
    {
        $data = $request->validate([
            'due_at' => 'required|date|after:now',
            'notes' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($loan, $data) {
            $lockedLoan = LibraryLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedLoan->status !== 'requested', 409, 'This request has already been processed.');

            $book = LibraryBook::whereKey($lockedLoan->book_id)->lockForUpdate()->firstOrFail();
            abort_unless($book->is_active && $book->available_copies > 0, 409, 'No copies are currently available.');

            $student = $lockedLoan->student;
            abort_unless(! $book->branch_id || (int) $book->branch_id === (int) $student->branch_id, 403,
                'This book is assigned to another branch.');

            $alreadyIssued = LibraryLoan::where('book_id', $book->id)
                ->where('student_id', $student->id)
                ->where('status', 'issued')
                ->exists();
            abort_if($alreadyIssued, 409, 'This student already has this book on loan.');

            $book->decrement('available_copies');
            $lockedLoan->update([
                'status' => 'issued',
                'issued_by' => auth()->id(),
                'issued_at' => now(),
                'due_at' => $data['due_at'],
                'notes' => $data['notes'] ?? $lockedLoan->notes,
            ]);
        });

        return back()->with('success', 'Book issued successfully.');
    }

    public function reject(Request $request, LibraryLoan $loan)
    {
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        DB::transaction(function () use ($loan, $data) {
            $lockedLoan = LibraryLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedLoan->status !== 'requested', 409, 'Only pending requests can be rejected.');

            $lockedLoan->update([
                'status' => 'rejected',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return back()->with('success', 'Library request rejected.');
    }

    public function returnBook(Request $request, LibraryLoan $loan)
    {
        $data = $request->validate([
            'fine_amount' => 'nullable|numeric|min:0|max:100000',
            'return_condition' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($loan, $data) {
            $lockedLoan = LibraryLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedLoan->status !== 'issued', 409, 'Only issued books can be returned.');

            $book = LibraryBook::whereKey($lockedLoan->book_id)->lockForUpdate()->firstOrFail();
            $book->update([
                'available_copies' => min($book->total_copies, $book->available_copies + 1),
            ]);

            $lockedLoan->update([
                'status' => 'returned',
                'returned_at' => now(),
                'fine_amount' => $data['fine_amount'] ?? 0,
                'return_condition' => $data['return_condition'] ?? null,
            ]);
        });

        return back()->with('success', 'Book return recorded.');
    }
}
