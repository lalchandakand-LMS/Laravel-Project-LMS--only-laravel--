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
            <label class="mt-2 block text-xs font-semibold text-slate-600">Hosted PDF / EPUB URL (free e-books only)</label>
            <input type="url" name="file_url" value="{{ old('file_url', str_starts_with((string) $ebook->file, 'http') ? $ebook->file : '') }}" placeholder="https://example.com/ebook.pdf" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">Paid e-books require a private upload (PDF / EPUB, max 50 MB). Public hosted URLs cannot enforce purchase access.</p>
            <button type="button" data-open-media-picker data-picker-url="{{ route('admin.media.index', ['picker' => 1, 'target' => 'cover_url', 'type' => 'image']) }}" class="mt-1 text-xs font-semibold text-emerald-700">Choose cover from Media Library →</button>
            @if ($ebook->exists && ! $ebook->is_free && is_string($ebook->file) && str_starts_with(strtolower($ebook->file), 'http'))
                <p class="mt-2 rounded-lg bg-rose-50 p-2 text-xs text-rose-800">Security warning: this paid title currently points to a public URL. The protected download route will not redirect buyers to it. Migrate the original file to private storage through a controlled content migration before restoring paid downloads.</p>
            @endif
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
@push('scripts')
<script>
(() => {
    let pickerWindow = null;
    document.querySelectorAll('[data-open-media-picker]').forEach(button => button.addEventListener('click', () => {
        pickerWindow = window.open(button.dataset.pickerUrl, 'lmsMediaPicker', 'width=1200,height=850,resizable=yes,scrollbars=yes');
        if (pickerWindow) pickerWindow.focus();
    }));
    window.addEventListener('message', event => {
        if (event.origin !== window.location.origin || event.source !== pickerWindow) return;
        if (!event.data || event.data.type !== 'lms-media-selected' || typeof event.data.url !== 'string') return;
        const allowed = ['thumbnail_url', 'image_url', 'cover_url'];
        if (!allowed.includes(event.data.target)) return;
        const field = document.querySelector('[name="' + event.data.target + '"]');
        if (!field) return;
        field.value = event.data.url;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        pickerWindow = null;
    });
})();
</script>
@endpush

@endsection