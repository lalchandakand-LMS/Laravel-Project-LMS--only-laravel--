@extends('layouts.app')
@section('title', 'Shop')
@section('content')
<div class="aec-public-page mx-auto max-w-7xl">
    @include('partials.public-page-hero', [
        'eyebrow' => "Alom's learning store",
        'title' => 'Books & learning products',
        'description' => 'Find course books and learning materials from Alom’s English Center, ready to support your next lesson.',
        'actionLabel' => 'Browse e-books',
        'actionUrl' => route('ebooks.index'),
    ])
    <div class="mb-5 mt-8 flex flex-wrap items-end justify-between gap-3">
        <div><p class="text-xs font-extrabold uppercase tracking-[.16em] text-orange-700">The learning store</p><h2 class="mt-1 text-2xl font-extrabold text-slate-900">Explore the collection</h2></div>
        <p class="text-sm text-slate-500">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</p>
    </div>
    @if($products->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-600">No products are available right now.</div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($products as $product)
                <article class="aec-motion-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <a href="{{ route('products.show', $product->slug) }}" class="block overflow-hidden bg-slate-100"><img src="{{ $product->imageUrl() }}" alt="{{ $product->title }}" loading="lazy" class="h-52 w-full object-cover transition duration-500 hover:scale-[1.04]"></a>
                    <div class="p-5"><h3 class="line-clamp-2 font-bold text-slate-900"><a href="{{ route('products.show', $product->slug) }}">{{ $product->title }}</a></h3><p class="mt-2 text-xl font-black text-orange-700">৳{{ number_format($product->price, 2) }}</p><p class="mt-1 text-xs text-slate-500">{{ $product->availableQuantity() }} in stock</p>
                        <form method="POST" action="{{ route('cart.add', ['type' => 'product', 'id' => $product->id]) }}" class="mt-4">@csrf<button class="w-full rounded-full bg-slate-950 px-4 py-3 font-bold text-white transition hover:bg-orange-600">Add to cart</button></form>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-8">{{ $products->links() }}</div>
    @endif
</div>
@endsection
