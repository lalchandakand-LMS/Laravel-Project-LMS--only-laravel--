@extends('layouts.student')
@section('title', 'Library')
@section('heading', 'Library')
@section('content')
<div class="mb-5"><h2 class="text-xl font-extrabold text-slate-900">Book catalogue</h2><p class="mt-1 text-sm text-slate-500">Request a physical book from your branch library. The library team will confirm issue and due date.</p></div>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($books as $book)
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-2"><span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ $book->category ?: 'General' }}</span><span class="text-xs font-semibold {{ $book->available_copies > 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $book->available_copies }} available</span></div>
            <h3 class="mt-3 text-lg font-extrabold text-slate-900">{{ $book->title }}</h3>
            <p class="mt-1 text-sm text-slate-600">by {{ $book->author }}</p>
            @if ($book->description)<p class="mt-3 line-clamp-3 text-sm text-slate-500">{{ $book->description }}</p>@endif
            <p class="mt-3 text-xs text-slate-400">{{ $book->branch?->name ?? 'Available to all branches' }} · Shelf {{ $book->shelf_location ?: '—' }}</p>
            @if ($book->available_copies > 0)
                <form method="POST" action="{{ route('student.library.request', $book) }}" class="mt-4">@csrf<button class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Request this book</button></form>
            @else
                <button disabled class="mt-4 w-full cursor-not-allowed rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-400">Currently unavailable</button>
            @endif
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-3">No books are available in your branch catalogue yet.</div>
    @endforelse
</div>
@if ($books->hasPages())<div class="mt-5">{{ $books->links() }}</div>@endif

<section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="font-extrabold text-slate-900">My recent library requests & loans</h2>
    <div class="mt-4 space-y-3">
        @forelse ($loans as $loan)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                <div><p class="font-bold text-slate-800">{{ $loan->book?->title }}</p><p class="text-xs text-slate-500">{{ ucfirst($loan->status) }} @if($loan->due_at) · Due {{ $loan->due_at->format('d M Y') }} @endif</p></div>
                @if ($loan->status === 'requested')
                    <form method="POST" action="{{ route('student.library.cancel', $loan) }}">@csrf<button class="text-xs font-bold text-rose-700">Cancel request</button></form>
                @elseif ($loan->status === 'issued')
                    <span class="text-xs font-bold {{ $loan->isOverdue() ? 'text-rose-700' : 'text-sky-700' }}">{{ $loan->isOverdue() ? 'Overdue' : 'On loan' }}</span>
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-500">You have no library requests or loans.</p>
        @endforelse
    </div>
</section>
@endsection
