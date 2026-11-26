@extends('layouts.admin')
@section('title', $book->exists ? 'Edit library book' : 'Add library book')
@section('heading', $book->exists ? 'Edit library book' : 'Add library book')
@section('content')
<form method="POST" action="{{ $book->exists ? route('admin.library.books.update', $book) : route('admin.library.books.store') }}" class="max-w-3xl space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($book->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="mb-1 block text-sm font-semibold">Title</label><input name="title" value="{{ old('title', $book->title) }}" required maxlength="180" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
        <div><label class="mb-1 block text-sm font-semibold">Author</label><input name="author" value="{{ old('author', $book->author) }}" required maxlength="180" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('author')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
        <div><label class="mb-1 block text-sm font-semibold">ISBN</label><input name="isbn" value="{{ old('isbn', $book->isbn) }}" maxlength="30" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        <div><label class="mb-1 block text-sm font-semibold">Category</label><input name="category" value="{{ old('category', $book->category) }}" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        <div><label class="mb-1 block text-sm font-semibold">Branch</label><select name="branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All branches</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $book->branch_id) == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-sm font-semibold">Shelf location</label><input name="shelf_location" value="{{ old('shelf_location', $book->shelf_location) }}" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        <div><label class="mb-1 block text-sm font-semibold">Total physical copies</label><input type="number" name="total_copies" min="1" max="100000" required value="{{ old('total_copies', $book->total_copies ?? 1) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('total_copies')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
    </div>
    <div><label class="mb-1 block text-sm font-semibold">Description</label><textarea name="description" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description', $book->description) }}</textarea></div>
    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $book->exists ? $book->is_active : true))> Available in student catalogue</label>
    <div class="flex gap-2"><button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save book</button><a href="{{ route('admin.library.books.index') }}" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold">Cancel</a></div>
</form>
@endsection
