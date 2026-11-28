@php($c = $course)
<article class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
    <a href="{{ route('courses.show', $c->slug) }}" class="relative block aspect-video overflow-hidden bg-slate-100">
        <img src="{{ $c->thumbnail_url }}" alt="{{ $c->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @if($c->has_discount)
            <span class="absolute left-3 top-3 rounded-full {{ $c->activeFlashSale() ? 'bg-emerald-700' : 'bg-slate-900/85' }} px-2.5 py-1 text-[11px] font-extrabold text-white">{{ $c->activeFlashSale() ? 'FLASH SALE' : '-'.$c->discount_percent.'%' }}</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-4 sm:p-5">
        <h3 class="line-clamp-2 min-h-12 font-extrabold leading-snug text-slate-900">
            <a href="{{ route('courses.show', $c->slug) }}" class="transition hover:text-emerald-700">{{ $c->title }}</a>
        </h3>
        <p class="mt-2 text-sm text-slate-500">{{ optional($c->instructor)->name ?: "Alom's English Center" }}</p>
        <div class="mt-auto flex items-baseline gap-2 border-t border-slate-100 pt-4">
            <span class="text-lg font-extrabold text-emerald-700">{{ $c->final_price <= 0 ? 'Free' : '৳'.number_format($c->final_price) }}</span>
            @if($c->has_discount)
                <span class="text-sm text-slate-400 line-through">৳{{ number_format($c->price) }}</span>
            @endif
        </div>
    </div>
</article>
