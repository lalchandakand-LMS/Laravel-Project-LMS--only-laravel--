@extends('layouts.admin')
@section('title', 'Media library')
@section('heading', 'Media library')
@section('content')
<div class="grid gap-5 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-900">Upload image or PDF</h2>
        <p class="mt-1 text-xs text-slate-500">Upload a reusable public asset. Images and PDFs up to 25 MB are supported. Paid course documents should still use the protected lesson/e-book upload fields.</p>
        <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
            @csrf
            <div><label class="mb-1 block text-sm font-semibold">Title</label><input name="title" required maxlength="180" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Alt text (for images)</label><input name="alt_text" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Choose file</label><input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" required class="w-full text-sm"></div>
            @error('file')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            <button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Upload media</button>
        </form>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-900">Add external media URL</h2>
        <p class="mt-1 text-xs text-slate-500">For files hosted on your website, CDN, YouTube image CDN or another trusted HTTPS host. The LMS stores the URL; it does not download remote content.</p>
        <form method="POST" action="{{ route('admin.media.links.store') }}" class="mt-4 space-y-3">
            @csrf
            <div><label class="mb-1 block text-sm font-semibold">Title</label><input name="title" required maxlength="180" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold">Image / PDF URL</label><input type="url" name="url" required placeholder="https://example.com/image.webp" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm font-semibold">Type (optional)</label><select name="mime_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Unknown / auto</option><option value="image/jpeg">JPEG image</option><option value="image/png">PNG image</option><option value="image/webp">WebP image</option><option value="image/gif">GIF image</option><option value="application/pdf">PDF document</option></select></div>
                <div><label class="mb-1 block text-sm font-semibold">Alt text</label><input name="alt_text" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            </div>
            <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700">Save external link</button>
        </form>
    </section>
</div>

<form method="GET" class="mt-6 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Search media..." class="min-w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All media</option><option value="image" @selected(request('type') === 'image')>Images</option><option value="pdf" @selected(request('type') === 'pdf')>PDFs</option></select>
    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filter</button>
</form>

<div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
    @forelse ($assets as $asset)
        <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex h-36 items-center justify-center bg-slate-100">
                @if ($asset->isImage())
                    <img src="{{ $asset->publicUrl() }}" alt="{{ $asset->alt_text ?: $asset->title }}" class="h-full w-full object-contain">
                @else
                    <div class="text-center"><div class="text-4xl">📄</div><p class="mt-2 text-xs font-bold text-slate-600">PDF / Document</p></div>
                @endif
            </div>
            <div class="p-3">
                <p class="truncate text-sm font-bold text-slate-900">{{ $asset->title }}</p>
                <p class="mt-1 truncate text-xs text-slate-500">{{ $asset->original_name ?: $asset->url }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" data-copy-url="{{ $asset->publicUrl() }}" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800">Copy URL</button>
                    @if ($asset->publicUrl())<a href="{{ $asset->publicUrl() }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold">Open</a>@endif
                    <form method="POST" action="{{ route('admin.media.destroy', $asset) }}" onsubmit="return confirm('Remove this media item? Existing pages using its URL may stop displaying.')">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700">Delete</button></form>
                </div>
            </div>
        </article>
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3 2xl:col-span-4">No media uploaded yet.</div>
    @endforelse
</div>
<div class="mt-5">{{ $assets->links() }}</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-copy-url]').forEach(button => button.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(button.dataset.copyUrl); button.textContent = 'Copied!'; }
    catch { window.prompt('Copy this media URL:', button.dataset.copyUrl); }
}));
</script>
@endpush
