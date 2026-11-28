@extends('layouts.app')
@section('title', 'E-books')
@section('content')
<div class="aec-public-page mx-auto max-w-7xl">
    @include('partials.public-page-hero', [
        'eyebrow' => 'Study resources',
        'title' => 'E-books & class notes',
        'description' => 'Keep useful PDF notes, question banks and solution books close while you study.',
        'actionLabel' => 'Visit the learning store',
        'actionUrl' => route('products.index'),
    ])
    <div class="mb-5 mt-8"><p class="text-xs font-extrabold uppercase tracking-[.16em] text-orange-700">Read, revise, repeat</p><h2 class="mt-1 text-2xl font-extrabold text-slate-900">Resources for your study plan</h2></div>
    @if ($ebooks->isNotEmpty())
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($ebooks as $ebook)
                <article class="aec-motion-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <a href="{{ route('ebooks.show', $ebook->slug) }}" class="block overflow-hidden bg-slate-100"><img src="{{ $ebook->coverUrl() }}" alt="{{ $ebook->title }}" loading="lazy" class="h-56 w-full object-cover transition duration-500 hover:scale-[1.04]"></a>
                    <div class="p-4">
                        <h3 class="line-clamp-2 font-bold text-slate-900"><a href="{{ route('ebooks.show', $ebook->slug) }}" class="hover:text-emerald-700">{{ $ebook->title }}</a></h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $ebook->pages }} pages · PDF</p>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <div>
                                @if($ebook->has_discount)
                                    <span class="font-extrabold text-slate-900">৳{{ number_format($ebook->final_price, 2) }}</span>
                                    <span class="ml-1 text-xs text-slate-400 line-through">৳{{ number_format($ebook->price, 2) }}</span>
                                @else
                                    <span class="font-extrabold text-slate-900">{{ $ebook->is_free ? 'Free' : '৳'.number_format($ebook->price, 2) }}</span>
                                @endif
                            </div>
                            <a href="{{ route('ebooks.show', $ebook->slug) }}" class="rounded-full bg-emerald-700 px-4 py-2 text-xs font-bold text-white transition hover:bg-emerald-800">View</a>
                        </div>
                        @if($ebook->has_discount)<p class="mt-1 text-xs font-semibold text-rose-700">{{ $ebook->activeFlashSale()?->name }}</p>@endif
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-10">{{ $ebooks->links() }}</div>
    @else
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"><p class="text-lg font-bold text-slate-900">New study resources are on the way</p><p class="mt-2 text-sm text-slate-600">Please check back soon for e-books and class notes.</p></div>
    @endif
</div>
@endsection
