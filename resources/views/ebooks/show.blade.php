@extends('layouts.app')
@section('title', $ebook->title)
@section('meta_description', $ebook->description)
@section('meta_image', $ebook->coverUrl())
@section('meta_image_alt', $ebook->title)
@section('og_type', 'book')
@push('structured_data')
<script type="application/ld+json"><?php echo json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Book',
    'name' => $ebook->title,
    'description' => $ebook->description,
    'image' => $ebook->coverUrl(),
    'url' => route('ebooks.show', $ebook->slug),
    'numberOfPages' => $ebook->pages,
    'bookFormat' => 'https://schema.org/EBook',
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'BDT',
        'price' => number_format((float) $ebook->final_price, 2, '.', ''),
        'availability' => 'https://schema.org/InStock',
        'url' => route('ebooks.show', $ebook->slug),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
@endpush
@section('content')
<div class="aec-detail-page min-h-[70vh] px-4 py-10 sm:py-12">
<div class="mx-auto grid max-w-6xl gap-8 md:grid-cols-[320px_1fr]">
    <div class="rounded-3xl border border-slate-200 bg-white p-2 shadow-sm"><img src="{{ $ebook->coverUrl() }}" alt="{{ $ebook->title }}" class="w-full rounded-2xl object-cover"></div>
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
        <a href="{{ route('ebooks.index') }}" class="text-sm font-bold text-orange-700">← E-books & notes</a>
        <p class="mt-5 text-xs font-extrabold uppercase tracking-[.16em] text-orange-700">Digital study resource</p>
        <h1 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $ebook->title }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $ebook->pages }} pages · PDF</p>
        <div class="mt-6 whitespace-pre-line text-slate-600">{{ $ebook->description }}</div>

        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-6">
            @if($ebook->has_discount)
                <div class="flex flex-wrap items-center gap-3"><span class="text-3xl font-extrabold text-slate-900">৳{{ number_format($ebook->final_price, 2) }}</span><span class="text-lg text-slate-400 line-through">৳{{ number_format($ebook->price, 2) }}</span><span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-700">{{ $ebook->activeFlashSale()?->name }}</span></div>
            @else
                <p class="text-3xl font-extrabold text-slate-900">{{ $ebook->is_free ? 'Free' : '৳'.number_format($ebook->price, 2) }}</p>
            @endif
            @auth
                @if ($owned)
                    <a href="{{ route('student.ebooks.read', $ebook->slug) }}" class="mt-4 inline-block rounded-full bg-emerald-700 px-6 py-3 font-bold text-white hover:bg-emerald-800">Read now →</a>
                @else
                    <form method="POST" action="{{ route('cart.add', ['type' => 'ebook', 'id' => $ebook->id]) }}" class="mt-4">
                        @csrf
                        <button class="rounded-full bg-emerald-700 px-6 py-3 font-bold text-white hover:bg-emerald-800">Add to cart</button>
                    </form>
                @endif
            @else
                <a href="{{ route('login') }}" class="mt-4 inline-block rounded-full bg-emerald-700 px-6 py-3 font-bold text-white hover:bg-emerald-800">Log in to buy</a>
            @endauth
        </div>
    </div>
</div>
</div>
@endsection
