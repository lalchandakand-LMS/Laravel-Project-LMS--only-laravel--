<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($brandName = \App\Models\Setting::get('site_name', "Alom's English Center"))
    @php($brandDescription = trim(strip_tags($__env->yieldContent('meta_description', \App\Models\Setting::get('site_tagline', 'Practical English learning, speaking practice, grammar and vocabulary.')))))
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $brandName }}">
    <meta property="og:title" content="@yield('title', $brandName) — {{ $brandName }}">
    <meta property="og:description" content="{{ $brandDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ media_url(setting('logo'), asset('images/aec-logo.svg')) }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="@yield('title', $brandName) — {{ $brandName }}">
    <meta name="twitter:description" content="{{ $brandDescription }}">
    <title>@yield('title', \App\Models\Setting::get('site_name', "Alom's English Center")) — {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</title>
    <meta name="description" content="{{ $brandDescription }}">
    @include('partials.assets')
    <style>
        :root {
            --aec-primary: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', (string) setting('site_primary_color')) && strtolower((string) setting('site_primary_color')) !== '#ea580c' ? setting('site_primary_color') : '#06b6d4' }};
            --aec-primary-dark: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', (string) setting('site_secondary_color')) && strtolower((string) setting('site_secondary_color')) !== '#9a3412' ? setting('site_secondary_color') : '#0d9488' }};
        }
        .bg-emerald-700,.bg-emerald-600,.bg-emerald-500 { background-color: var(--aec-primary) !important; }
        .bg-emerald-400 { background-color: color-mix(in srgb, var(--aec-primary) 82%, white) !important; }
        .bg-emerald-50,[class~="bg-emerald-400/10"] { background-color: color-mix(in srgb, var(--aec-primary) 9%, white) !important; }
        .bg-orange-700,.bg-orange-600,.bg-orange-500 { background-color: var(--aec-primary) !important; }
        .bg-orange-400 { background-color: color-mix(in srgb, var(--aec-primary) 82%, white) !important; }
        .bg-orange-50,.bg-orange-100 { background-color: color-mix(in srgb, var(--aec-primary) 10%, white) !important; }
        .text-orange-800,.text-orange-700,.text-orange-600,.text-orange-500 { color: var(--aec-primary-dark) !important; }
        .text-orange-400,.text-orange-300,.text-orange-200 { color: color-mix(in srgb, var(--aec-primary) 65%, white) !important; }
        .border-orange-100,.border-orange-200,.border-orange-400 { border-color: color-mix(in srgb, var(--aec-primary) 22%, transparent) !important; }
        [class~="hover:bg-orange-800"]:hover,[class~="hover:bg-orange-700"]:hover,[class~="hover:bg-orange-400"]:hover { background-color: var(--aec-primary-dark) !important; }
        [class~="hover:text-orange-700"]:hover,[class~="hover:text-orange-800"]:hover { color: var(--aec-primary-dark) !important; }
        .to-orange-950 { --tw-gradient-to: var(--aec-primary-dark) var(--tw-gradient-to-position) !important; }
        .text-emerald-800,.text-emerald-700,.text-emerald-600,.text-emerald-500 { color: var(--aec-primary-dark) !important; }
        .text-emerald-400,.text-emerald-300,.text-emerald-200 { color: color-mix(in srgb, var(--aec-primary) 65%, white) !important; }
        .border-emerald-400,[class~="border-emerald-400/30"],.border-emerald-500 { border-color: color-mix(in srgb, var(--aec-primary) 42%, transparent) !important; }
        [class~="hover:bg-emerald-800"]:hover,[class~="hover:bg-emerald-700"]:hover,[class~="hover:bg-emerald-400"]:hover { background-color: var(--aec-primary-dark) !important; }
        [class~="hover:text-emerald-700"]:hover,[class~="hover:text-emerald-800"]:hover { color: var(--aec-primary-dark) !important; }
        .to-emerald-950 { --tw-gradient-to: var(--aec-primary-dark) var(--tw-gradient-to-position) !important; }
        [class~="shadow-emerald-500/20"] { --tw-shadow-color: color-mix(in srgb, var(--aec-primary) 20%, transparent) !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
@php($brandLoaderEnabled = \App\Models\Setting::get('brand_loader_enabled', '0') === '1')
@if ($brandLoaderEnabled)
<style>
    #brand-page-loader{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:{{ \App\Models\Setting::get('brand_loader_bg','#ffffff') }};opacity:1;visibility:visible;transition:opacity .25s ease,visibility .25s ease}
    #brand-page-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
    #brand-page-loader .brand-loader-inner{display:flex;flex-direction:column;align-items:center;gap:16px;text-align:center;padding:24px}
    #brand-page-loader .brand-loader-logo{width:76px;height:76px;object-fit:contain;border-radius:16px}
    #brand-page-loader .brand-loader-name{font-weight:800;font-size:1.15rem;color:{{ \App\Models\Setting::get('brand_loader_text_color','#0f172a') }}}
    #brand-page-loader .brand-loader-message{font-size:.9rem;color:{{ \App\Models\Setting::get('brand_loader_text_color','#0f172a') }}}
    #brand-page-loader .brand-spinner{width:42px;height:42px;border:4px solid color-mix(in srgb, {{ \App\Models\Setting::get('brand_loader_color', '#06b6d4') }} 18%, transparent);border-top-color:{{ \App\Models\Setting::get('brand_loader_color', '#06b6d4') }};border-radius:50%;animation:brand-loader-spin .8s linear infinite}
    #brand-page-loader[data-style="dots"] .brand-spinner{width:10px;height:10px;border:0;border-radius:50%;background:{{ \App\Models\Setting::get('brand_loader_color', '#06b6d4') }};box-shadow:-16px 0 0 {{ \App\Models\Setting::get('brand_loader_color', '#06b6d4') }},16px 0 0 {{ \App\Models\Setting::get('brand_loader_color', '#06b6d4') }};animation:brand-loader-dots .8s ease-in-out infinite alternate}
    #brand-page-loader[data-style="pulse"] .brand-spinner{border:0;background:{{ \App\Models\Setting::get('brand_loader_color', '#06b6d4') }};animation:brand-loader-pulse 1s ease-in-out infinite}
    @keyframes brand-loader-spin{to{transform:rotate(360deg)}}
    @keyframes brand-loader-dots{to{transform:scale(1.25);opacity:.55}}
    @keyframes brand-loader-pulse{50%{transform:scale(.72);opacity:.55}}
    @media(prefers-reduced-motion:reduce){#brand-page-loader .brand-spinner{animation:none}}
</style>
@endif

@if ($brandLoaderEnabled)
<div id="brand-page-loader" role="status" aria-live="polite" aria-label="Loading website" data-style="{{ \App\Models\Setting::get('brand_loader_style','ring') }}">
    <div class="brand-loader-inner">
        <img class="brand-loader-logo" src="{{ \App\Models\Setting::get('logo') ? media_url(\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="">
        <div class="brand-loader-name">{{ $brandName }}</div>
        <div class="brand-spinner" aria-hidden="true"></div>
        <p class="brand-loader-message">{{ \App\Models\Setting::get('brand_loader_message','Preparing your learning experience...') }}</p>
    </div>
</div>
<noscript><style>#brand-page-loader{display:none!important}</style></noscript>
<script>
(function(){
    var loader=document.getElementById('brand-page-loader'); if(!loader)return;
    var started=Date.now(), minimum={{ (int) \App\Models\Setting::get('brand_loader_min_time','250') }}, hidden=false;
    function hide(){if(hidden)return;hidden=true;var wait=Math.max(0,minimum-(Date.now()-started));window.setTimeout(function(){loader.classList.add('is-hidden');window.setTimeout(function(){loader.remove()},350)},wait);}
    if(document.readyState==='complete'){hide()}else{window.addEventListener('load',hide,{once:true});}
    window.setTimeout(hide,8000);
})();
</script>
@endif


@include('partials.public-navigation')

<main class="min-h-[70vh]">
    @include('partials.flash')
    @yield('content')
</main>

<footer class="mt-0 border-t border-slate-800" style="background-color: {{ \App\Models\Setting::get('footer_bg', '#071b26') }}; color: {{ \App\Models\Setting::get('footer_text', '#cbd5e1') }}">
    <div class="mx-auto grid max-w-7xl gap-9 px-4 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="flex items-center gap-2"><img src="{{ \App\Models\Setting::get('logo') ? media_url(\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="{{ $brandName }}" class="h-10 w-10 rounded-lg object-contain"><span class="font-extrabold text-white">{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</span></div>
            <p class="mt-4 max-w-xs text-sm leading-relaxed text-slate-400">{{ \App\Models\Setting::get('site_tagline', 'Quality education for everyone, everywhere.') }}</p>
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach (['facebook' => ['Facebook','f'], 'youtube' => ['YouTube','▶'], 'instagram' => ['Instagram','◎']] as $key => [$label,$symbol])
                    @if ($url = \App\Models\Setting::get($key))
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}" class="grid h-10 w-10 place-items-center rounded-full border border-white/15 bg-white/5 text-sm font-extrabold text-cyan-200 transition hover:bg-cyan-500 hover:text-slate-950">{{ $symbol }}</a>
                    @endif
                @endforeach
            </div>
        </div>
        <div>
            <h4 class="text-sm font-extrabold uppercase tracking-wider text-white">Links</h4>
            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                <li><a class="hover:text-cyan-300" href="{{ route('courses.index') }}">All Courses</a></li>
                <li><a class="hover:text-cyan-300" href="{{ route('blog.index') }}">Blog & News</a></li>
                <li><a class="hover:text-cyan-300" href="{{ route('coaching-admission.create') }}">Coaching Admission</a></li>
                <li><a class="hover:text-cyan-300" href="{{ route('ebooks.index') }}">E-Books</a></li>
                <li><a class="hover:text-cyan-300" href="{{ route('about') }}">About us</a></li>
                @foreach (\App\Models\MenuItem::publicNavigation('footer', 'desktop') as $footerItem)
                    <li><a class="hover:text-cyan-300" href="{{ str_starts_with($footerItem->url, 'http') ? $footerItem->url : url($footerItem->url) }}" @if($footerItem->open_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $footerItem->label }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-extrabold uppercase tracking-wider text-white">Company Details</h4>
            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                <li>{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</li>
                @if (\App\Models\Setting::get('company_license'))<li>License: {{ \App\Models\Setting::get('company_license') }}</li>@endif
                @if (\App\Models\Setting::get('site_address'))<li>{{ \App\Models\Setting::get('site_address') }}</li>@endif
                <li><a class="hover:text-cyan-300" href="{{ route('contact') }}">Contact company</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-extrabold uppercase tracking-wider text-white">Contact</h4>
            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                <li>{{ \App\Models\Setting::get('site_email', 'support@example.com') }}</li>
                <li>{{ \App\Models\Setting::get('site_phone', '+880 1700-000000') }}</li>
                @if (\App\Models\Setting::get('site_address'))<li>{{ \App\Models\Setting::get('site_address') }}</li>@endif
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-3 px-4 py-5 text-xs text-slate-400 sm:justify-between">
            <span class="font-bold uppercase tracking-wider text-slate-300">Pay With</span>
            <div class="flex flex-wrap items-center justify-center gap-2">
                @foreach (['VISA','Mastercard','bKash','Nagad','SSLCommerz'] as $gateway)
                    <span class="rounded-md border border-white/15 bg-white px-3 py-2 text-xs font-extrabold {{ $gateway === 'bKash' ? 'text-pink-600' : ($gateway === 'Nagad' ? 'text-orange-600' : 'text-slate-800') }}">{{ $gateway }}</span>
                @endforeach
            </div>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-xs text-slate-400">
            <span>© {{ date('Y') }} {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}. {{ \App\Models\Setting::get('footer_copyright', 'All rights reserved.') }}</span>
            <nav aria-label="Legal policies" class="flex flex-wrap gap-x-4 gap-y-2"><a href="{{ url('/terms-and-conditions') }}" class="hover:text-cyan-300">Terms & Conditions</a><a href="{{ url('/privacy-policy') }}" class="hover:text-cyan-300">Privacy Policy</a><a href="{{ url('/refund-policy') }}" class="hover:text-cyan-300">Refund Policy</a></nav>
        </div>
    </div>
    @if (\App\Models\Setting::get('developer_show', '0') === '1' && \App\Models\Setting::get('developer_name'))
    <div class="border-t border-white/10 px-4 py-3 text-center text-xs" style="background-color: {{ \App\Models\Setting::get('developer_bg', '#0f172a') }}; color: {{ \App\Models\Setting::get('developer_text_color', '#ffffff') }}">{{ \App\Models\Setting::get('developer_text', 'Developed by') }} @if ($developerUrl = \App\Models\Setting::get('developer_url'))<a href="{{ $developerUrl }}" target="_blank" rel="noopener noreferrer" class="font-bold underline">{{ \App\Models\Setting::get('developer_name') }}</a>@else <strong>{{ \App\Models\Setting::get('developer_name') }}</strong>@endif</div>
    @endif
</footer>@stack('scripts')
</body>
</html>
