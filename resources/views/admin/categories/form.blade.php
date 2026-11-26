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
                <a href="{{ route('admin.media.index') }}" class="mt-1 inline-block text-xs font-semibold text-emerald-700">Open Media Library →</a>
                @if($category->image)<label class="mt-2 flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>@endif
            </div>
            <div><label class="mb-1 block text-sm font-semibold">Display order</label><input type="number" min="0" name="position" value="{{ old('position', $category->position ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></div>
        </div>
        <label class="mt-4 flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))> Active / visible on website</label>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-bold text-slate-900">SEO settings</h2><div class="mt-4 grid gap-4 md:grid-cols-2"><div><label class="mb-1 block text-sm font-semibold">SEO title</label><input name="meta_title" maxlength="180" value="{{ old('meta_title', $category->meta_title) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></div><div><label class="mb-1 block text-sm font-semibold">Meta description</label><textarea name="meta_description" maxlength="300" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('meta_description', $category->meta_description) }}</textarea></div></div></section>
    <div class="flex gap-3"><button class="rounded-lg bg-orange-600 px-6 py-2.5 font-bold text-white">Save category</button><a href="{{ route('admin.categories.index') }}" class="rounded-lg border border-slate-300 px-6 py-2.5 font-semibold">Cancel</a></div>
</form>
@endsection
