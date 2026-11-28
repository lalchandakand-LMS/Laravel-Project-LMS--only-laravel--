@extends('layouts.admin')
@section('title', $category->exists ? 'Edit category' : 'Create category')
@section('heading', $category->exists ? 'Edit category' : 'Create category / subcategory')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="max-w-4xl space-y-5">
    @csrf
    @if($category->exists) @method('PUT') @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold text-slate-900">Basic information</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-sm font-semibold">Category name *</label><input name="name" required maxlength="120" value="{{ old('name', $category->name) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></div>
            <div><label class="mb-1 block text-sm font-semibold">URL slug</label><input name="slug" value="{{ old('slug', $category->slug) }}" placeholder="auto-generated-from-name" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></div>
            <div><label class="mb-1 block text-sm font-semibold">Parent category</label><select name="parent_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"><option value="">— Root category —</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((string)old('parent_id', request('parent_id', $category->parent_id)) === (string)$parent->id)>{{ $parent->parent?->name ? $parent->parent->name.' / ' : '' }}{{ $parent->name }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">Choose any category to create nested subcategories. Circular nesting is blocked.</p></div>
            <div><label class="mb-1 block text-sm font-semibold">Icon / Emoji</label><input name="icon" value="{{ old('icon', $category->icon) }}" placeholder="📘" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></div>
            <div class="md:col-span-2"><label class="mb-1 block text-sm font-semibold">Description</label><textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('description', $category->description) }}</textarea></div>
            <div>
                <label class="mb-1 block text-sm font-semibold">Category image</label>
                @if($category->image)<img src="{{ $category->image_url }}" class="mb-2 h-20 rounded-lg object-cover" alt="Current category image">@endif
                <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                <label class="mt-2 block text-xs font-semibold text-slate-600">Or use an image URL</label>
                <input type="url" name="image_url" value="{{ old('image_url', str_starts_with((string) $category->image, 'http') ? $category->image : '') }}" placeholder="https://example.com/category.webp" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="button" data-open-media-picker data-picker-url="{{ route('admin.media.index', ['picker' => 1, 'target' => 'image_url']) }}" class="mt-1 text-xs font-semibold text-emerald-700">Choose from Media Library →</button>
                @if($category->image)<label class="mt-2 flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>@endif
            </div>
            <div><label class="mb-1 block text-sm font-semibold">Display order</label><input type="number" min="0" name="position" value="{{ old('position', $category->position ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></div>
        </div>
        <label class="mt-4 flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))> Active / visible on website</label>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-bold text-slate-900">SEO settings</h2><div class="mt-4 grid gap-4 md:grid-cols-2"><div><label class="mb-1 block text-sm font-semibold">SEO title</label><input name="meta_title" maxlength="180" value="{{ old('meta_title', $category->meta_title) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></div><div><label class="mb-1 block text-sm font-semibold">Meta description</label><textarea name="meta_description" maxlength="300" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('meta_description', $category->meta_description) }}</textarea></div></div></section>
    <div class="flex gap-3"><button class="rounded-lg bg-orange-600 px-6 py-2.5 font-bold text-white">Save category</button><a href="{{ route('admin.categories.index') }}" class="rounded-lg border border-slate-300 px-6 py-2.5 font-semibold">Cancel</a></div>
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