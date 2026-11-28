@extends('layouts.admin')
@section('title', $post->exists ? 'Edit post' : 'Create post')
@section('heading', $post->exists ? 'Edit post' : 'Create post')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('admin.posts.update',$post) : route('admin.posts.store') }}" class="max-w-5xl space-y-5">
@csrf @if($post->exists) @method('PUT') @endif
<section class="rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Post content</h2><div class="mt-4 grid gap-4 md:grid-cols-2">
<div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold">Title *</label><input required maxlength="180" name="title" value="{{ old('title',$post->title) }}" class="w-full rounded-lg border px-3 py-2.5"></div>
<div><label class="mb-1 block text-sm font-semibold">URL slug</label><input name="slug" value="{{ old('slug',$post->slug) }}" class="w-full rounded-lg border px-3 py-2.5"></div>
<div><label class="mb-1 block text-sm font-semibold">Category</label><select name="category_id" class="w-full rounded-lg border px-3 py-2.5"><option value="">Uncategorised</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id',$post->category_id)===(string)$category->id)>{{ $category->parent?->name ? $category->parent->name.' / ' : '' }}{{ $category->name }}</option>@endforeach</select></div>
<div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold">Short description / excerpt</label><textarea name="excerpt" maxlength="500" rows="2" class="w-full rounded-lg border px-3 py-2.5">{{ old('excerpt',$post->excerpt) }}</textarea></div>
<div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold">Full content</label><textarea name="content" rows="12" class="w-full rounded-lg border px-3 py-2.5">{{ old('content',$post->content) }}</textarea></div>
<div><label class="mb-1 block text-sm font-semibold">Video URL</label><input type="url" name="video_url" value="{{ old('video_url',$post->video_url) }}" placeholder="https://..." class="w-full rounded-lg border px-3 py-2.5"></div>
<div><label class="mb-1 block text-sm font-semibold">Tags (comma separated, max 20)</label><input name="tags" value="{{ old('tags',implode(', ',$post->tags ?? [])) }}" class="w-full rounded-lg border px-3 py-2.5"></div>
<div><label class="mb-1 block text-sm font-semibold">Thumbnail image</label>@if($post->thumbnail)<img src="{{ media_url($post->thumbnail) }}" class="mb-2 h-24 rounded-lg object-cover" alt="Current thumbnail">@endif<input type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full rounded-lg border px-3 py-2"><input name="thumbnail_url" type="url" placeholder="Or image URL" class="mt-2 w-full rounded-lg border px-3 py-2"><button type="button" data-open-media-picker data-picker-url="{{ route('admin.media.index', ['picker' => 1, 'target' => 'thumbnail_url', 'type' => 'image']) }}" class="mt-1 text-xs font-semibold text-emerald-700">Choose from Media Library →</button>@if($post->thumbnail)<label class="mt-2 block text-sm"><input type="checkbox" name="remove_thumbnail" value="1"> Remove thumbnail</label>@endif</div>
<div><label class="mb-1 block text-sm font-semibold">Publishing</label><label class="flex items-center gap-2"><input type="checkbox" name="is_published" value="1" @checked(old('is_published',$post->is_published))> Publish now</label><p class="mt-2 text-xs text-slate-500">Unpublished posts remain drafts and are hidden from visitors.</p></div>
</div></section>
<section class="rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">SEO</h2><div class="mt-4 grid gap-4 md:grid-cols-2"><div><label class="mb-1 block text-sm font-semibold">Meta title</label><input name="meta_title" maxlength="180" value="{{ old('meta_title',$post->meta_title) }}" class="w-full rounded-lg border px-3 py-2"></div><div><label class="mb-1 block text-sm font-semibold">Meta description</label><textarea name="meta_description" maxlength="300" rows="2" class="w-full rounded-lg border px-3 py-2">{{ old('meta_description',$post->meta_description) }}</textarea></div></div></section>
<div class="flex gap-3"><button class="rounded-lg bg-orange-600 px-6 py-2.5 font-bold text-white">Save post</button><a href="{{ route('admin.posts.index') }}" class="rounded-lg border px-6 py-2.5 font-semibold">Cancel</a></div>
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