@extends('layouts.admin')
@section('title', $product->exists ? 'Edit Product' : 'New Product')
@section('heading', $product->exists ? 'Edit Product' : 'New Product')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="max-w-4xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
@csrf @if($product->exists) @method('PUT') @endif
<div class="grid gap-5 md:grid-cols-2">
<label class="block md:col-span-2"><span class="mb-1 block text-sm font-semibold">Product title</span><input name="title" required maxlength="180" value="{{ old('title', $product->title) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></label>
<label class="block"><span class="mb-1 block text-sm font-semibold">Slug (optional)</span><input name="slug" value="{{ old('slug', $product->slug) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></label>
<label class="block"><span class="mb-1 block text-sm font-semibold">SKU</span><input name="sku" value="{{ old('sku', $product->sku) }}" maxlength="80" class="w-full rounded-lg border border-slate-300 px-3 py-2"></label>
<label class="block"><span class="mb-1 block text-sm font-semibold">Price (BDT)</span><input type="number" name="price" min="0" step="0.01" required value="{{ old('price', $product->price ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></label>
<label class="block"><span class="mb-1 block text-sm font-semibold">Total stock</span><input type="number" name="stock_quantity" min="0" required value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">@if($product->exists)<span class="mt-1 block text-xs text-slate-500">Reserved for unpaid orders: {{ $product->reserved_quantity }}. Total stock cannot be reduced below this amount.</span>@endif</label>
<label class="block md:col-span-2"><span class="mb-1 block text-sm font-semibold">Description</span><textarea name="description" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('description', $product->description) }}</textarea></label>
<label class="block md:col-span-2"><span class="mb-1 block text-sm font-semibold">Product image</span>@if($product->image)<img src="{{ $product->imageUrl() }}" alt="" class="mb-3 h-28 w-28 rounded-xl object-cover">@endif<input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full text-sm"><span class="mt-1 block text-xs text-slate-500">JPG, PNG or WebP; maximum 4 MB.</span></label>
<label class="flex items-center gap-2 md:col-span-2"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $product->is_published))> <span class="text-sm font-semibold">Publish in public shop</span></label>
</div>
<div class="flex gap-3"><button class="rounded-lg bg-orange-600 px-5 py-2.5 font-bold text-white">Save product</button><a href="{{ route('admin.products.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 font-semibold">Cancel</a></div>
</form>
@endsection
