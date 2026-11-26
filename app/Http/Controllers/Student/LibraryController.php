<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use Illuminate\Http\Request;

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
        abort_unless($book->is_active, 404);
        abort_unless(! $book->branch_id || (int) $book->branch_id === (int) $user->branch_id, 404);
        abort_if($book->available_copies < 1, 409, 'No copies are currently available.');

        $existing = LibraryLoan::where('book_id', $book->id)
            ->where('student_id', $user->id)
            ->whereIn('status', ['requested', 'issued'])
            ->exists();
        abort_if($existing, 409, 'You already have a pending request or active loan for this book.');

        LibraryLoan::create([
            'book_id' => $book->id,
            'student_id' => $user->id,
            'requested_at' => now(),
            'status' => 'requested',
        ]);

        return redirect()->route('student.library.index')->with('success', 'Book request submitted. Wait for the library team to approve it.');
    }

    public function cancelRequest(LibraryLoan $loan)
    {
        abort_unless((int) $loan->student_id === (int) auth()->id(), 404);
        abort_if($loan->status !== 'requested', 409, 'Only pending book requests can be cancelled.');

        $loan->update(['status' => 'cancelled']);

        return back()->with('success', 'Book request cancelled.');
    }
}
