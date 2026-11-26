@extends('layouts.app')
@section('title', $ebook->title)
@section('content')
<div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 md:grid-cols-[320px_1fr]">
    <img src="{{ $ebook->coverUrl() }}" alt="{{ $ebook->title }}" class="w-full rounded-2xl border border-slate-200 object-cover shadow-sm">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900">{{ $ebook->title }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $ebook->pages }} pages · PDF</p>
        <div class="mt-6 whitespace-pre-line text-slate-600">{{ $ebook->description }}</div>

        <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @if($ebook->has_discount)
                <div class="flex flex-wrap items-center gap-3"><span class="text-3xl font-extrabold text-slate-900">৳{{ number_format($ebook->final_price, 2) }}</span><span class="text-lg text-slate-400 line-through">৳{{ number_format($ebook->price, 2) }}</span><span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-700">{{ $ebook->activeFlashSale()?->name }}</span></div>
            @else
                <p class="text-3xl font-extrabold text-slate-900">{{ $ebook->is_free ? 'Free' : '৳'.number_format($ebook->price, 2) }}</p>
            @endif
            @auth
                @if ($owned)
                    <a href="{{ route('student.ebooks.read', $ebook->slug) }}" class="mt-4 inline-block rounded-xl bg-emerald-700 px-6 py-3 font-bold text-white hover:bg-emerald-800">Read now →</a>
                @else
                    <form method="POST" action="{{ route('cart.add', ['type' => 'ebook', 'id' => $ebook->id]) }}" class="mt-4">
                        @csrf
                        <button class="rounded-xl bg-emerald-700 px-6 py-3 font-bold text-white hover:bg-emerald-800">Add to cart</button>
                    </form>
                @endif
            @else
                <a href="{{ route('login') }}" class="mt-4 inline-block rounded-xl bg-emerald-700 px-6 py-3 font-bold text-white hover:bg-emerald-800">Log in to buy</a>
            @endauth
        </div>
    </div>
</div>
@endsection
