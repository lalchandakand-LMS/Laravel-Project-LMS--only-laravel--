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
<div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 md:grid-cols-2">
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white"><img src="{{ $product->imageUrl() }}" alt="{{ $product->title }}" class="h-full max-h-[560px] w-full object-cover"></div>
    <div class="py-4"><a href="{{ route('products.index') }}" class="text-sm font-semibold text-orange-700">← Shop</a><h1 class="mt-4 text-3xl font-black text-slate-950">{{ $product->title }}</h1><p class="mt-4 text-2xl font-black text-orange-700">৳{{ number_format($product->price, 2) }}</p><p class="mt-2 text-sm text-slate-500">{{ $product->availableQuantity() }} available</p>
        @if($product->description)<div class="prose prose-slate mt-6 max-w-none">{!! nl2br(e($product->description)) !!}</div>@endif
        <form method="POST" action="{{ route('cart.add', ['type' => 'product', 'id' => $product->id]) }}" class="mt-8">@csrf<button class="rounded-xl bg-orange-600 px-6 py-3 font-bold text-white hover:bg-orange-700">Add to cart</button></form>
    </div>
</div>
@endsection
