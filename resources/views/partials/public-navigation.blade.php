@php
    $desktopMenu = \App\Models\MenuItem::publicNavigation('header', 'desktop');
    $mobileMenu = \App\Models\MenuItem::publicNavigation('header', 'mobile');
    $menuUrl = function ($item) {
        $role = auth()->user()->role ?? null;
        $studentRoles = ['student', 'instructor', 'admin'];
        if ($item->url === '/my/my-courses' && (! $role || ! in_array($role, $studentRoles, true))) return route('courses.index');
        if (in_array($item->url, ['/my/exams', '/my/profile'], true) && (! $role || ! in_array($role, $studentRoles, true))) {
            return $role ? auth()->user()->dashboardRoute() : route('login');
        }
        return str_starts_with($item->url, 'http://') || str_starts_with($item->url, 'https://') ? $item->url : url($item->url);
    };
    $menuActive = function ($item) { $path = trim(parse_url($item->url, PHP_URL_PATH) ?: '/', '/'); return $path === '' ? request()->is('/') : (request()->is($path) || request()->is($path.'/*')); };
@endphp

<header class="sticky top-0 z-50 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
        <a href="{{ route('home') }}" class="flex min-w-0 shrink-0 items-center gap-2" aria-label="{{ $brandName }} home">
            <img src="{{ \App\Models\Setting::get('logo') ? media_url(\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="" class="h-10 w-10 rounded-lg object-contain">
            <span class="max-w-[12rem] truncate text-base font-extrabold tracking-tight text-slate-900 sm:text-lg">{{ $brandName }}</span>
        </a>
        <form action="{{ route('courses.index') }}" class="ml-auto hidden max-w-xs flex-1 lg:block">
            <label class="sr-only" for="desktop-course-search">Search courses</label>
            <input id="desktop-course-search" name="q" value="{{ request('q') }}" placeholder="Search courses…" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100">
        </form>
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('cart.index') }}" aria-label="Shopping cart" class="relative rounded-xl border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                <span aria-hidden="true">🛒</span>
                @php($cartCount = count((array) session('cart', [])))
                @if ($cartCount)<span class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-emerald-700 px-1 text-[10px] font-bold text-white">{{ $cartCount }}</span>@endif
            </a>
            @auth
                <a href="{{ auth()->user()->dashboardRoute() }}" class="hidden rounded-xl bg-emerald-700 px-3 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 sm:inline-flex">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:inline-flex">Login</a>
                <a href="{{ route('register') }}" class="hidden rounded-xl bg-emerald-700 px-3 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 sm:inline-flex">Sign up</a>
            @endauth
            <button id="mobile-menu-open" type="button" aria-label="Open navigation menu" aria-controls="mobile-menu" aria-expanded="false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-xl text-slate-800 transition hover:bg-slate-100 lg:hidden"><span aria-hidden="true">☰</span></button>
        </div>
    </div>
    <nav aria-label="Main navigation" class="mx-auto hidden max-w-7xl items-center justify-center gap-1 overflow-x-auto px-3 pb-2 lg:flex">
        @foreach ($desktopMenu as $item)
            <div class="group relative">
                <a href="{{ $menuUrl($item) }}" @if ($menuActive($item)) aria-current="page" @endif @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="inline-flex items-center gap-1 whitespace-nowrap rounded-xl px-3 py-2 text-sm font-semibold transition {{ $menuActive($item) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">{{ $item->icon }} {{ $item->label }} @if($item->children->isNotEmpty()) <span aria-hidden="true">⌄</span> @endif</a>
                @if($item->children->isNotEmpty())
                    <div class="invisible absolute left-0 top-full z-50 min-w-48 rounded-xl border border-slate-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                        @foreach($item->children as $child)
                            <a href="{{ $menuUrl($child) }}" @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">{{ $child->icon }} {{ $child->label }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </nav>
</header>

<div id="mobile-menu-overlay" class="fixed inset-0 z-[60] hidden bg-slate-950/50 opacity-0 transition-opacity duration-300" aria-hidden="true"></div>
<aside id="mobile-menu" class="fixed inset-y-0 right-0 z-[70] flex w-[min(88vw,22rem)] translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-out lg:hidden" aria-label="Mobile navigation" aria-hidden="true" inert>
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <span class="font-extrabold text-slate-900">{{ $brandName }}</span>
        <button id="mobile-menu-close" type="button" aria-label="Close navigation menu" class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-xl hover:bg-slate-100">×</button>
    </div>
    <div class="overflow-y-auto px-4 py-4">
        <form action="{{ route('courses.index') }}" class="mb-4">
            <label class="sr-only" for="mobile-course-search">Search courses</label>
            <input id="mobile-course-search" name="q" value="{{ request('q') }}" placeholder="Search courses…" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
        </form>
        <nav aria-label="Mobile main navigation" class="grid gap-1">
            @foreach ($mobileMenu as $item)
                <a href="{{ $menuUrl($item) }}" @if ($menuActive($item)) aria-current="page" @endif @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition {{ $menuActive($item) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-100' }}">{{ $item->icon }} {{ $item->label }} <span aria-hidden="true" class="text-slate-400">›</span></a>
                @foreach($item->children as $child)
                    <a href="{{ $menuUrl($child) }}" @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="ml-5 block rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">{{ $child->icon }} {{ $child->label }}</a>
                @endforeach
            @endforeach
        </nav>        @auth
            <a href="{{ auth()->user()->dashboardRoute() }}" class="mt-4 block rounded-xl bg-emerald-700 px-4 py-3 text-center text-sm font-bold text-white">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-rose-600">Log out</button></form>
        @else
            <div class="mt-4 grid grid-cols-2 gap-2"><a href="{{ route('login') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold text-slate-700">Login</a><a href="{{ route('register') }}" class="rounded-xl bg-emerald-700 px-4 py-3 text-center text-sm font-bold text-white">Sign up</a></div>
        @endauth
    </div>
</aside>

@push('scripts')
<script>
(() => {
    const panel = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-menu-overlay');
    const openButton = document.getElementById('mobile-menu-open');
    const closeButton = document.getElementById('mobile-menu-close');
    if (!panel || !overlay || !openButton || !closeButton) return;
    let previousFocus = null;
    const open = () => {
        previousFocus = document.activeElement;
        panel.inert = false;
        panel.setAttribute('aria-hidden', 'false');
        overlay.classList.remove('hidden');
        requestAnimationFrame(() => { panel.classList.remove('translate-x-full'); overlay.classList.remove('opacity-0'); });
        openButton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
        closeButton.focus();
    };
    const close = () => {
        panel.classList.add('translate-x-full');
        overlay.classList.add('opacity-0');
        panel.setAttribute('aria-hidden', 'true');
        panel.inert = true;
        openButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
        window.setTimeout(() => overlay.classList.add('hidden'), 300);
        if (previousFocus) previousFocus.focus();
    };
    openButton.addEventListener('click', open);
    closeButton.addEventListener('click', close);
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.inert) close();
        if (event.key === 'Tab' && !panel.inert) {
            const focusable = [...panel.querySelectorAll('a, button, input')].filter(el => !el.disabled);
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
})();
</script>
@endpush
