@extends('layouts.admin')
@section('title', $ebook->exists ? 'Edit e-book' : 'New e-book')
@section('heading', $ebook->exists ? 'Edit e-book' : 'New e-book')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $ebook->exists ? route('admin.ebooks.update', $ebook) : route('admin.ebooks.store') }}"
      class="max-w-2xl space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($ebook->exists) @method('PUT') @endif
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Title *</label>
        <input name="title" value="{{ old('title', $ebook->title) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-slate-700">Description</label>
        <textarea name="description" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('description', $ebook->description) }}</textarea>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Price (৳)</label>
            <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $ebook->price ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Pages</label>
            <input type="number" min="0" name="pages" value="{{ old('pages', $ebook->pages ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Cover / Thumbnail</label>
            @if ($ebook->cover)<img src="{{ $ebook->coverUrl() }}" alt="Current e-book cover" class="mb-2 h-28 rounded-lg object-contain">@endif
            <input type="file" name="cover" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full text-xs">
            <label class="mt-2 block text-xs font-semibold text-slate-600">Or paste a cover image URL</label>
            <input type="url" name="cover_url" value="{{ old('cover_url', str_starts_with((string) $ebook->cover, 'http') ? $ebook->cover : '') }}" placeholder="https://example.com/cover.webp" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">JPG, PNG, WebP or GIF · Maximum 4 MB</p>
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">PDF / EPUB file</label>
            <input type="file" name="file" accept=".pdf,.epub,application/pdf,application/epub+zip" class="w-full text-xs">
            <label class="mt-2 block text-xs font-semibold text-slate-600">Or use a hosted PDF / EPUB URL</label>
            <input type="url" name="file_url" value="{{ old('file_url', str_starts_with((string) $ebook->file, 'http') ? $ebook->file : '') }}" placeholder="https://example.com/ebook.pdf" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">PDF / EPUB · Maximum 50 MB · Stored privately</p>
            <a href="{{ route('admin.media.index') }}" class="mt-1 inline-block text-xs font-semibold text-emerald-700">Open Media Library →</a>
            @if ($ebook->exists && $ebook->purchases()->exists())
                <p class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-800">This e-book has purchase history. Its PDF/EPUB cannot be replaced, so existing buyers keep the exact purchased file. You can still update its title, description, price and cover.</p>
            @endif
        </div>
    </div>
    <div class="flex gap-5 text-sm font-semibold text-slate-700">
        <label class="flex items-center gap-2"><input type="checkbox" name="is_free" value="1" @checked(old('is_free', $ebook->is_free)) class="rounded text-emerald-700"> Free</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $ebook->is_published ?? true)) class="rounded text-emerald-700"> Published</label>
    </div>
    <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save e-book</button>
</form>
@endsection
