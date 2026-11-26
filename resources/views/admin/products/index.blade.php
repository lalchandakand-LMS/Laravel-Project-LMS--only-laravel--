@extends('layouts.admin')
@section('title', 'Physical Products')
@section('heading', 'Physical Products')
@section('actions')<a href="{{ route('admin.products.create') }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white">+ Add product</a>@endsection
@section('content')
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-4">Product</th><th class="p-4">SKU</th><th class="p-4">Price</th><th class="p-4">Stock</th><th class="p-4">Status</th><th class="p-4">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($products as $product)<tr><td class="p-4"><div class="flex items-center gap-3"><img src="{{ $product->imageUrl() }}" class="h-12 w-12 rounded-lg object-cover" alt=""><div><div class="font-bold text-slate-900">{{ $product->title }}</div><div class="text-xs text-slate-500">{{ $product->slug }}</div></div></div></td><td class="p-4">{{ $product->sku ?: '—' }}</td><td class="p-4 font-semibold">৳{{ number_format($product->price, 2) }}</td><td class="p-4">{{ $product->stock_quantity }}</td><td class="p-4">{{ $product->trashed() ? 'Archived' : ($product->is_published ? 'Published' : 'Draft') }}</td><td class="p-4"><div class="flex flex-wrap gap-3"><a class="font-semibold text-orange-700" href="{{ route('admin.products.edit', $product->id) }}">Edit</a>
@if($product->trashed())<form method="POST" action="{{ route('admin.products.restore', $product->id) }}">@csrf<button class="font-semibold text-emerald-700">Restore</button></form>@else<form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Archive this product?')">@csrf @method('DELETE')<button class="font-semibold text-rose-700">Archive</button></form>@endif
</div></td></tr>@empty<tr><td colspan="6" class="p-8 text-center text-slate-500">No products yet.</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $products->links() }}</div>
@endsection
