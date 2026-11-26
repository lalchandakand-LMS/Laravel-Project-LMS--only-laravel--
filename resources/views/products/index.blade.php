@extends('layouts.app')
@section('title', 'Shop')
@section('content')
<section class="mx-auto max-w-7xl px-4 py-12">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-bold uppercase tracking-widest text-orange-600">Alom's Shop</p><h1 class="mt-2 text-3xl font-black text-slate-950">Books & Learning Products</h1><p class="mt-2 text-slate-600">Physical products from Alom's English Center.</p></div>
    </div>
    @if($products->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-600">No products are available right now.</div>
    @else
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($products as $product)
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <a href="{{ route('products.show', $product->slug) }}"><img src="{{ $product->imageUrl() }}" alt="{{ $product->title }}" class="h-52 w-full object-cover"></a>
                    <div class="p-5"><h2 class="line-clamp-2 font-bold text-slate-900"><a href="{{ route('products.show', $product->slug) }}">{{ $product->title }}</a></h2><p class="mt-2 text-xl font-black text-orange-700">৳{{ number_format($product->price, 2) }}</p><p class="mt-1 text-xs text-slate-500">{{ $product->stock_quantity }} in stock</p>
                        <form method="POST" action="{{ route('cart.add', ['type' => 'product', 'id' => $product->id]) }}" class="mt-4">@csrf<button class="w-full rounded-xl bg-slate-950 px-4 py-3 font-bold text-white hover:bg-orange-600">Add to cart</button></form>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-8">{{ $products->links() }}</div>
    @endif
</section>
@endsection
