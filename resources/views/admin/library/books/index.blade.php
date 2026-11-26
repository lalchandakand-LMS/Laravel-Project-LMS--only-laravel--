@extends('layouts.admin')
@section('title', 'Library books')
@section('heading', 'Library book catalogue')
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search title, author or ISBN" class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white">Search</button>
    </form>
    <div class="flex gap-2">
        <a href="{{ route('admin.library.loans.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Manage loans</a>
        <a href="{{ route('admin.library.books.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">+ Add book</a>
    </div>
</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Book</th><th class="px-4 py-3">Branch / shelf</th><th class="px-4 py-3">Copies</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($books as $book)
                <tr>
                    <td class="px-4 py-3"><p class="font-bold text-slate-900">{{ $book->title }}</p><p class="text-xs text-slate-500">{{ $book->author }} · {{ $book->isbn ?: 'No ISBN' }}</p></td>
                    <td class="px-4 py-3 text-slate-600">{{ $book->branch?->name ?? 'All branches' }}<p class="text-xs text-slate-400">{{ $book->shelf_location }}</p></td>
                    <td class="px-4 py-3"><strong>{{ $book->available_copies }}</strong> / {{ $book->total_copies }} available</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $book->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $book->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.library.books.edit', $book) }}" class="font-bold text-indigo-700">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No library books found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $books->links() }}</div>
@endsection
