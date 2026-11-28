@extends('layouts.app')
@section('title', $product->title)
@section('meta_description', $product->description)
@section('meta_image', $product->imageUrl())
@section('meta_image_alt', $product->title)
@section('og_type', 'product')
@push('structured_data')
<script type="application/ld+json"><?php echo json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->title,
    'description' => $product->description,
    'image' => $product->imageUrl(),
    'url' => route('products.show', $product->slug),
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'BDT',
        'price' => number_format((float) $product->price, 2, '.', ''),
        'availability' => $product->availableQuantity() > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => route('products.show', $product->slug),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
@endpush
@section('content')
<div class="aec-detail-page min-h-[70vh] px-4 py-10 sm:py-12">
<div class="mx-auto grid max-w-6xl gap-8 md:grid-cols-2">
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white p-2 shadow-sm"><img src="{{ $product->imageUrl() }}" alt="{{ $product->title }}" class="max-h-[560px] w-full rounded-2xl object-cover"></div>
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9"><a href="{{ route('products.index') }}" class="text-sm font-bold text-orange-700">← Shop</a><p class="mt-5 text-xs font-extrabold uppercase tracking-[.16em] text-orange-700">Learning store</p><h1 class="mt-2 text-3xl font-black text-slate-950">{{ $product->title }}</h1><p class="mt-4 text-2xl font-black text-orange-700">৳{{ number_format($product->price, 2) }}</p><p class="mt-2 text-sm text-slate-500">{{ $product->availableQuantity() }} available</p>
        @if($product->description)<div class="prose prose-slate mt-6 max-w-none">{!! nl2br(e($product->description)) !!}</div>@endif
        <form method="POST" action="{{ route('cart.add', ['type' => 'product', 'id' => $product->id]) }}" class="mt-8">@csrf<button class="rounded-full bg-orange-600 px-7 py-3 font-bold text-white transition hover:-translate-y-0.5 hover:bg-orange-700">Add to cart</button></form>
    </div>
</div>
</div>
@endsection
