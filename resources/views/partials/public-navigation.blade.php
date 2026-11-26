@php
    $desktopMenu = \App\Models\MenuItem::publicNavigation('header', 'desktop');
    $mobileMenu = \App\Models\MenuItem::publicNavigation('header', 'mobile');
    $menuUrl = function ($item) {
        $role = auth()->user()->role ?? null;
        $studentRoles = ['student', 'instructor', 'admin'];
        if ($item->url === '/my/my-courses' && (! $role || ! in_array($role, $studentRoles, true))) return route('courses.index');
        if (in_array($item->url, ['/my/exams', '/my/profile'], true) && (! $role || ! in_array($role, $studentRoles, true))) return $role ? auth()->user()->dashboardRoute() : route('login');
        return str_starts_with($item->url, 'http://') || str_starts_with($item->url, 'https://') ? $item->url : url($item->url);
    };
    $menuActive = function ($item) { $path = trim(parse_url($item->url, PHP_URL_PATH) ?: '/', '/'); return $path === '' ? request()->is('/') : (request()->is($path) || request()->is($path.'/*')); };
@endphp

<header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 shadow-sm backdrop-blur">
    <div class="mx-auto flex min-h-[76px] max-w-7xl items-center gap-5 px-4">
        <a href="{{ route('home') }}" class="flex min-w-0 shrink-0 items-center gap-2" aria-label="{{ $brandName }} home">
            <img src="{{ \App\Models\Setting::get('logo') ? media_url(\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="" class="h-10 w-10 rounded-lg object-contain">
            <span class="max-w-[11rem] truncate text-base font-extrabold tracking-tight text-slate-900 sm:text-lg">{{ $brandName }}</span>
        </a>
        <nav aria-label="Main navigation" class="hidden min-w-0 flex-1 items-center justify-center gap-1 xl:flex">
            @foreach ($desktopMenu as $item)
                <div class="group relative">
                    <a href="{{ $menuUrl($item) }}" @if ($menuActive($item)) aria-current="page" @endif @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="inline-flex items-center gap-1 whitespace-nowrap rounded-xl px-3 py-2 text-sm font-semibold transition {{ $menuActive($item) ? 'bg-orange-50 text-orange-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">{{ $item->icon }} {{ $item->label }} @if($item->children->isNotEmpty()) <span aria-hidden="true">⌄</span> @endif</a>
                    @if($item->children->isNotEmpty())
                        <div class="invisible absolute left-0 top-full z-50 min-w-48 rounded-xl border border-slate-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                            @foreach($item->children as $child)
                                <a href="{{ $menuUrl($child) }}" @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-orange-50">{{ $child->icon }} {{ $child->label }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </nav>
        <div class="ml-auto flex shrink-0 items-center gap-2">
            @auth
                <a href="{{ route('cart.index') }}" aria-label="Shopping cart" class="rounded-full border border-orange-200 px-4 py-2.5 text-sm font-bold text-orange-800 transition hover:bg-orange-50">Cart</a>
                <a href="{{ auth()->user()->dashboardRoute() }}" class="rounded-full bg-orange-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-orange-700">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-full bg-orange-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-orange-700">Login</a>
            @endauth
            <button id="mobile-menu-open" type="button" aria-label="Open navigation menu" aria-controls="mobile-menu" aria-expanded="false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-xl text-slate-800 transition hover:bg-slate-100 xl:hidden"><span aria-hidden="true">☰</span></button>
        </div>
    </div>
</header>

<div id="mobile-menu-overlay" class="fixed inset-0 z-[60] hidden bg-slate-950/50 opacity-0 transition-opacity duration-300" aria-hidden="true"></div>
<aside id="mobile-menu" class="fixed inset-y-0 right-0 z-[70] flex w-[min(88vw,22rem)] translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-out xl:hidden" aria-label="Mobile navigation" aria-hidden="true" inert>
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><span class="font-extrabold text-slate-900">{{ $brandName }}</span><button id="mobile-menu-close" type="button" aria-label="Close navigation menu" class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-xl hover:bg-slate-100">×</button></div>
    <div class="overflow-y-auto px-4 py-4">
        <nav aria-label="Mobile main navigation" class="grid gap-1">
            @foreach ($mobileMenu as $item)
                <a href="{{ $menuUrl($item) }}" @if ($menuActive($item)) aria-current="page" @endif @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition {{ $menuActive($item) ? 'bg-orange-50 text-orange-800' : 'text-slate-700 hover:bg-slate-100' }}">{{ $item->icon }} {{ $item->label }} <span aria-hidden="true" class="text-slate-400">›</span></a>
                @foreach($item->children as $child)
                    <a href="{{ $menuUrl($child) }}" @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="ml-5 block rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">{{ $child->icon }} {{ $child->label }}</a>
                @endforeach
            @endforeach
        </nav>
        @auth
            <a href="{{ route('cart.index') }}" class="mt-4 block rounded-xl border border-orange-200 px-4 py-3 text-center text-sm font-bold text-orange-800">Shopping cart</a>
            <a href="{{ auth()->user()->dashboardRoute() }}" class="mt-2 block rounded-xl bg-orange-600 px-4 py-3 text-center text-sm font-bold text-white">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-rose-600">Log out</button></form>
        @endauth
    </div>
</aside>

@push('scripts')
<script>
(() => {
    const panel=document.getElementById('mobile-menu'),overlay=document.getElementById('mobile-menu-overlay'),openButton=document.getElementById('mobile-menu-open'),closeButton=document.getElementById('mobile-menu-close');
    if(!panel||!overlay||!openButton||!closeButton)return;
    let previousFocus=null;
    const open=()=>{previousFocus=document.activeElement;panel.inert=false;panel.setAttribute('aria-hidden','false');overlay.classList.remove('hidden');requestAnimationFrame(()=>{panel.classList.remove('translate-x-full');overlay.classList.remove('opacity-0')});openButton.setAttribute('aria-expanded','true');document.body.classList.add('overflow-hidden');closeButton.focus()};
    const close=()=>{panel.classList.add('translate-x-full');overlay.classList.add('opacity-0');panel.setAttribute('aria-hidden','true');panel.inert=true;openButton.setAttribute('aria-expanded','false');document.body.classList.remove('overflow-hidden');window.setTimeout(()=>overlay.classList.add('hidden'),300);if(previousFocus)previousFocus.focus()};
    openButton.addEventListener('click',open);closeButton.addEventListener('click',close);overlay.addEventListener('click',close);
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!panel.inert)close();if(event.key==='Tab'&&!panel.inert){const focusable=[...panel.querySelectorAll('a,button')].filter(el=>!el.disabled),first=focusable[0],last=focusable[focusable.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}}});
})();
</script>
@endpush
