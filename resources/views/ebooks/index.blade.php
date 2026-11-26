@extends('layouts.app')
@section('title', 'E-books')
@section('content')
<div class="mx-auto max-w-7xl px-4 py-12">
    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">E-books & class notes</h1>
    <p class="mt-1 text-slate-500">Downloadable PDF notes, question banks and solution books.</p>

    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($ebooks as $ebook)
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
                <a href="{{ route('ebooks.show', $ebook->slug) }}">
                    <img src="{{ $ebook->coverUrl() }}" alt="{{ $ebook->title }}" class="h-56 w-full object-cover">
                </a>
                <div class="p-4">
                    <h3 class="line-clamp-2 font-bold text-slate-900"><a href="{{ route('ebooks.show', $ebook->slug) }}" class="hover:text-emerald-700">{{ $ebook->title }}</a></h3>
                    <p class="mt-1 text-xs text-slate-500">{{ $ebook->pages }} pages</p>
                    <div class="mt-3 flex items-center justify-between">
                        <div>
                            @if($ebook->has_discount)
                                <span class="font-extrabold text-slate-900">৳{{ number_format($ebook->final_price, 2) }}</span>
                                <span class="ml-1 text-xs text-slate-400 line-through">৳{{ number_format($ebook->price, 2) }}</span>
                            @else
                                <span class="font-extrabold text-slate-900">{{ $ebook->is_free ? 'Free' : '৳'.number_format($ebook->price, 2) }}</span>
                            @endif
                        </div>
                        <a href="{{ route('ebooks.show', $ebook->slug) }}" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-800">View</a>
                    </div>
                    @if($ebook->has_discount)<p class="mt-1 text-xs font-semibold text-rose-700">{{ $ebook->activeFlashSale()?->name }}</p>@endif
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-10">{{ $ebooks->links() }}</div>
</div>
@endsection
