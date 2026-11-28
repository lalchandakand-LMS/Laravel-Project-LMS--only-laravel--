@php
    $desktopMenu = \App\Models\MenuItem::publicNavigation('header', 'desktop');
    $desktopPrimaryMenu = $desktopMenu->take(3);
    $desktopOverflowMenu = $desktopMenu->skip(3);
    $mobileMenu = \App\Models\MenuItem::publicNavigation('header', 'mobile');
    $menuUrl = function ($item) {
        $role = auth()->user()->role ?? null;
        $studentRoles = ['student', 'instructor', 'admin'];
        if ($item->url === '/my/my-courses' && (! $role || ! in_array($role, $studentRoles, true))) return route('courses.index');
        if (in_array($item->url, ['/my/exams', '/my/profile'], true) && (! $role || ! in_array($role, $studentRoles, true))) return $role ? auth()->user()->dashboardRoute() : route('login');
        return str_starts_with($item->url, 'http://') || str_starts_with($item->url, 'https://') ? $item->url : url($item->url);
    };
    $menuActive = function ($item) { $path = trim(parse_url($item->url, PHP_URL_PATH) ?: '/', '/'); return $path === '' ? request()->is('/') : (request()->is($path) || request()->is($path.'/*')); };
    $promoEnabled = \App\Models\Setting::get('public_promo_enabled', '0') === '1' && filled(\App\Models\Setting::get('public_promo_text'));
    $phone = \App\Models\Setting::get('site_phone');
    $phoneHref = preg_replace('/[^0-9+]/', '', (string) $phone);
@endphp

@if ($promoEnabled)
    <aside id="public-promo" class="relative bg-[#0b1221] px-10 py-2 text-center text-xs font-semibold text-white sm:text-sm" data-promo-key="{{ sha1((string) \App\Models\Setting::get('public_promo_text')) }}">
        <span>{{ \App\Models\Setting::get('public_promo_text') }}</span>
        @if ($promoUrl = \App\Models\Setting::get('public_promo_url'))
            <a href="{{ str_starts_with($promoUrl, 'https://') ? $promoUrl : url($promoUrl) }}" class="ml-2 inline-flex items-center gap-1 font-extrabold text-emerald-300 underline underline-offset-2">{{ \App\Models\Setting::get('public_promo_button') ?: 'Learn more' }} @include('partials.icon', ['name' => 'arrow-right', 'class' => 'h-3.5 w-3.5'])</a>
        @endif
        <button type="button" id="public-promo-close" class="absolute right-3 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-full text-white/75 hover:bg-white/10 hover:text-white" aria-label="Dismiss announcement">×</button>
    </aside>
@endif

<header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 shadow-sm backdrop-blur">
    <div class="mx-auto flex min-h-[76px] max-w-7xl items-center gap-3 px-4 lg:gap-5">
        <a href="{{ route('home') }}" class="flex min-w-0 shrink-0 items-center gap-2" aria-label="{{ $brandName }} home">
            <img src="{{ \App\Models\Setting::get('logo') ? media_url(\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="" class="h-10 w-10 rounded-lg object-contain">
            <span class="max-w-[8rem] truncate text-base font-extrabold tracking-tight text-slate-900 sm:max-w-[11rem] sm:text-lg">{{ $brandName }}</span>
        </a>
        <nav aria-label="Main navigation" class="hidden min-w-0 items-center gap-1 2xl:flex">
            @foreach ($desktopPrimaryMenu as $item)
                @if ($item->children->isNotEmpty())
                    <details class="group relative" name="desktop-navigation">
                        <summary @if ($menuActive($item)) aria-current="page" @endif class="inline-flex cursor-pointer list-none items-center gap-1 whitespace-nowrap rounded-xl px-2.5 py-2 text-sm font-semibold transition marker:content-none {{ $menuActive($item) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">
                            @include('partials.icon', ['name' => 'book-open', 'class' => 'h-4 w-4']) {{ $item->label }} <span aria-hidden="true" class="transition group-open:rotate-180">⌄</span>
                        </summary>
                        <div class="absolute left-0 top-full z-50 mt-2 grid w-[min(38rem,80vw)] grid-cols-2 gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl">
                            @foreach($item->children as $child)
                                <a href="{{ $menuUrl($child) }}" @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="group/item flex items-start gap-3 rounded-xl p-3 transition hover:bg-emerald-50">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-800">@include('partials.icon', ['name' => 'graduation-cap'])</span>
                                    <span><strong class="block text-sm font-bold text-slate-900">{{ $child->label }}</strong><small class="mt-1 block text-xs leading-5 text-slate-500">Explore courses and learning resources</small></span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    <a href="{{ $menuUrl($item) }}" @if ($menuActive($item)) aria-current="page" @endif @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="inline-flex items-center gap-1 whitespace-nowrap rounded-xl px-2.5 py-2 text-sm font-semibold transition {{ $menuActive($item) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">{{ $item->label }}</a>
                @endif
            @endforeach
            @if ($desktopOverflowMenu->isNotEmpty())
                <details class="group relative" name="desktop-navigation">
                    <summary class="inline-flex cursor-pointer list-none items-center gap-1 whitespace-nowrap rounded-xl px-2.5 py-2 text-sm font-semibold text-slate-600 transition marker:content-none hover:bg-slate-100 hover:text-slate-950">More <span aria-hidden="true" class="transition group-open:rotate-180">⌄</span></summary>
                    <div class="absolute right-0 top-full z-50 mt-2 max-h-[70vh] w-[min(36rem,90vw)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl">
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($desktopOverflowMenu as $item)
                                <div class="rounded-xl border border-slate-100 p-3">
                                    <a href="{{ $menuUrl($item) }}" @if ($menuActive($item)) aria-current="page" @endif @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="block text-sm font-bold text-slate-900 hover:text-emerald-700">{{ $item->label }}</a>
                                    @if ($item->children->isNotEmpty())
                                        <div class="mt-2 grid gap-1 border-t border-slate-100 pt-2">
                                            @foreach ($item->children as $child)
                                                <a href="{{ $menuUrl($child) }}" @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="rounded-lg px-2 py-1.5 text-xs font-medium text-slate-600 hover:bg-emerald-50 hover:text-emerald-800">{{ $child->label }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endif
        </nav>
        <form method="GET" action="{{ route('courses.index') }}" role="search" class="ml-auto hidden min-w-0 max-w-[15rem] flex-1 items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-2 focus-within:border-emerald-500 focus-within:bg-white md:flex 2xl:ml-0 2xl:w-48 2xl:flex-none">
            @include('partials.icon', ['name' => 'search', 'class' => 'h-4 w-4 shrink-0 text-slate-400'])
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search courses" aria-label="Search courses" class="w-full border-0 bg-transparent px-2 text-sm outline-none ring-0 placeholder:text-slate-400 focus:ring-0">
        </form>
        <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
            <div class="hidden items-center rounded-full bg-slate-100 p-0.5 text-xs font-bold sm:flex" aria-label="Language">
                <a href="{{ route('language.switch', 'bn') }}" lang="bn" class="rounded-full px-2 py-1 {{ session('locale', 'en') === 'bn' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500' }}">BN</a>
                <a href="{{ route('language.switch', 'en') }}" lang="en" class="rounded-full px-2 py-1 {{ session('locale', 'en') === 'en' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500' }}">EN</a>
            </div>
            @if ($phone)
                <a href="tel:{{ $phoneHref }}" class="hidden items-center gap-1.5 rounded-full border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-800 2xl:inline-flex">@include('partials.icon', ['name' => 'phone', 'class' => 'h-4 w-4'])<span>{{ $phone }}</span></a>
            @endif
            @auth
                <a href="{{ route('cart.index') }}" aria-label="Shopping cart" class="hidden rounded-full border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 sm:inline-flex">Cart</a>
                <a href="{{ auth()->user()->dashboardRoute() }}" class="rounded-full bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-full bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700">Login</a>
            @endauth
            <button id="mobile-menu-open" type="button" aria-label="Open navigation menu" aria-controls="mobile-menu" aria-expanded="false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-700 transition hover:bg-slate-100 2xl:hidden"><span aria-hidden="true">☰</span></button>
        </div>
    </div>
</header>

<div id="mobile-menu-overlay" class="fixed inset-0 z-[60] hidden bg-slate-950/50 opacity-0 transition-opacity duration-300" aria-hidden="true"></div>
<aside id="mobile-menu" class="fixed inset-y-0 right-0 z-[70] flex w-[min(88vw,22rem)] translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-out 2xl:hidden" aria-label="Mobile navigation" aria-hidden="true" inert>
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><span class="font-extrabold text-slate-900">{{ $brandName }}</span><button id="mobile-menu-close" type="button" aria-label="Close navigation menu" class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-xl hover:bg-slate-100">×</button></div>
    <div class="overflow-y-auto px-4 py-4">
        <form method="GET" action="{{ route('courses.index') }}" role="search" class="mb-4 flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">@include('partials.icon', ['name' => 'search', 'class' => 'h-4 w-4 text-slate-400'])<input type="search" name="q" value="{{ request('q') }}" placeholder="Search courses" aria-label="Search courses" class="w-full border-0 bg-transparent px-2 text-sm outline-none focus:ring-0"></form>
        <div class="mb-3 flex items-center gap-2 text-xs font-bold"><a href="{{ route('language.switch', 'bn') }}" class="rounded-full border px-3 py-1.5">বাংলা</a><a href="{{ route('language.switch', 'en') }}" class="rounded-full border px-3 py-1.5">English</a>@if($phone)<a href="tel:{{ $phoneHref }}" class="ml-auto inline-flex items-center gap-1 text-emerald-700">@include('partials.icon', ['name' => 'phone', 'class' => 'h-4 w-4']){{ $phone }}</a>@endif</div>
        <nav aria-label="Mobile main navigation" class="grid gap-1">
            @foreach ($mobileMenu as $item)
                @if ($item->children->isNotEmpty())
                    <details class="group rounded-xl" name="mobile-navigation"><summary @if ($menuActive($item)) aria-current="page" @endif class="flex cursor-pointer list-none items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition marker:content-none {{ $menuActive($item) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">{{ $item->label }}<span aria-hidden="true" class="text-slate-400 transition group-open:rotate-90">›</span></summary><div class="ml-3 border-l border-slate-200 py-1 pl-3">@foreach($item->children as $child)<a href="{{ $menuUrl($child) }}" @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="block rounded-lg px-4 py-3 text-sm text-slate-600 hover:bg-slate-100">{{ $child->label }}<small class="mt-1 block text-xs text-slate-400">Explore learning resources</small></a>@endforeach</div></details>
                @else
                    <a href="{{ $menuUrl($item) }}" @if ($menuActive($item)) aria-current="page" @endif @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition {{ $menuActive($item) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">{{ $item->label }}<span aria-hidden="true" class="text-slate-400">›</span></a>
                @endif
            @endforeach
        </nav>
        @auth
            <a href="{{ route('cart.index') }}" class="mt-4 block rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold text-slate-700">Shopping cart</a>
            <a href="{{ auth()->user()->dashboardRoute() }}" class="mt-2 block rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-rose-600">Log out</button></form>
        @else
            <a href="{{ route('login') }}" class="mt-4 block rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white">Log in</a>
        @endauth
    </div>
</aside>

<script>
(() => {
    const promo=document.getElementById('public-promo'),promoButton=document.getElementById('public-promo-close');
    if(promo&&promoButton){const key='aec-promo-'+promo.dataset.promoKey;if(localStorage.getItem(key)==='hidden')promo.remove();promoButton.addEventListener('click',()=>{localStorage.setItem(key,'hidden');promo.remove()})}
    const panel=document.getElementById('mobile-menu'),overlay=document.getElementById('mobile-menu-overlay'),openButton=document.getElementById('mobile-menu-open'),closeButton=document.getElementById('mobile-menu-close');
    if(panel&&overlay&&openButton&&closeButton){let previousFocus=null;const open=()=>{previousFocus=document.activeElement;panel.inert=false;panel.setAttribute('aria-hidden','false');overlay.classList.remove('hidden');requestAnimationFrame(()=>{panel.classList.remove('translate-x-full');overlay.classList.remove('opacity-0')});openButton.setAttribute('aria-expanded','true');document.body.classList.add('overflow-hidden');closeButton.focus()};const close=()=>{panel.classList.add('translate-x-full');overlay.classList.add('opacity-0');panel.setAttribute('aria-hidden','true');panel.inert=true;openButton.setAttribute('aria-expanded','false');document.body.classList.remove('overflow-hidden');window.setTimeout(()=>overlay.classList.add('hidden'),300);if(previousFocus)previousFocus.focus()};openButton.addEventListener('click',open);closeButton.addEventListener('click',close);overlay.addEventListener('click',close);document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!panel.inert)close();if(event.key==='Tab'&&!panel.inert){const items=[...panel.querySelectorAll('a,button')].filter(el=>!el.disabled),first=items[0],last=items.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}}})}
    const menus=[...document.querySelectorAll('nav details[name="desktop-navigation"], nav details[name="mobile-navigation"]')];document.addEventListener('click',event=>menus.forEach(menu=>{if(menu.open&&!menu.contains(event.target))menu.open=false}));document.addEventListener('keydown',event=>{if(event.key!=='Escape')return;const opened=menus.filter(menu=>menu.open);opened.forEach(menu=>menu.open=false);opened.at(-1)?.querySelector('summary')?.focus()});
})();
</script>
