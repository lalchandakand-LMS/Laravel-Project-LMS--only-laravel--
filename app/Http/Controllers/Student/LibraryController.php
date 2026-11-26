<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use Illuminate\Support\Facades\DB;

class LibraryController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isStudent(), 403);

        $books = LibraryBook::with('branch')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $user->branch_id))
            ->orderBy('title')->paginate(20);

        $loans = $user->libraryLoans()->with('book')->latest()->take(10)->get();

        return view('student.library.index', compact('books', 'loans'));
    }

    public function requestBook(LibraryBook $book)
    {
        $user = auth()->user();
        abort_unless($user->isStudent(), 403);
        DB::transaction(function () use ($book, $user) {
            $lockedBook = LibraryBook::whereKey($book->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedBook->is_active, 404);
            abort_unless(! $lockedBook->branch_id || (int) $lockedBook->branch_id === (int) $user->branch_id, 404);
            abort_if($lockedBook->available_copies < 1, 409, 'No copies are currently available.');

            $existing = LibraryLoan::where('book_id', $lockedBook->id)
                ->where('student_id', $user->id)
                ->whereIn('status', ['requested', 'issued'])
                ->exists();
            abort_if($existing, 409, 'You already have a pending request or active loan for this book.');

            LibraryLoan::create([
                'book_id' => $lockedBook->id,
                'student_id' => $user->id,
                'requested_at' => now(),
                'status' => 'requested',
            ]);
        });

        return redirect()->route('student.library.index')->with('success', 'Book request submitted. Wait for the library team to approve it.');
    }

    public function cancelRequest(LibraryLoan $loan)
    {
        DB::transaction(function () use ($loan) {
            $lockedLoan = LibraryLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $lockedLoan->student_id === (int) auth()->id(), 404);
            abort_if($lockedLoan->status !== 'requested', 409, 'Only pending book requests can be cancelled.');

            $lockedLoan->update(['status' => 'cancelled']);
        });

        return back()->with('success', 'Book request cancelled.');
    }
}
