<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\LibraryBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryBookController extends Controller
{
    public function index(Request $request)
    {
        $books = LibraryBook::with('branch')
            ->when($request->filled('q'), fn ($query) => $query->where(function ($search) use ($request) {
                $term = '%'.$request->string('q').'%';
                $search->where('title', 'like', $term)
                    ->orWhere('author', 'like', $term)
                    ->orWhere('isbn', 'like', $term);
            }))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.library.books.index', compact('books'));
    }

    public function create()
    {
        return view('admin.library.books.form', [
            'book' => new LibraryBook(['total_copies' => 1, 'is_active' => true]),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['available_copies'] = $data['total_copies'];
        LibraryBook::create($data);

        return redirect()->route('admin.library.books.index')->with('success', 'Library book added.');
    }

    public function edit(LibraryBook $book)
    {
        return view('admin.library.books.form', [
            'book' => $book,
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, LibraryBook $book)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($book, $data) {
            $locked = LibraryBook::whereKey($book->id)->lockForUpdate()->firstOrFail();
            $issuedCopies = $locked->loans()->where('status', 'issued')->count();

            if ($data['total_copies'] < $issuedCopies) {
                throw ValidationException::withMessages([
                    'total_copies' => 'Total copies cannot be lower than the number of books currently issued.',
                ]);
            }

            $data['available_copies'] = $data['total_copies'] - $issuedCopies;
            $locked->update($data);
        });

        return redirect()->route('admin.library.books.index')->with('success', 'Library book updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'title' => 'required|string|max:180',
            'author' => 'required|string|max:180',
            'isbn' => 'nullable|string|max:30',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:5000',
            'shelf_location' => 'nullable|string|max:100',
            'total_copies' => 'required|integer|min:1|max:100000',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
