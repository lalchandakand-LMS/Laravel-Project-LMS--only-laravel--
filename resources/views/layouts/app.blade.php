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
    <meta property="og:image" content="{{ asset('images/aec-logo.svg') }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="@yield('title', $brandName) — {{ $brandName }}">
    <meta name="twitter:description" content="{{ $brandDescription }}">
    <title>@yield('title', \App\Models\Setting::get('site_name', "Alom's English Center")) — {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</title>
    <meta name="description" content="{{ $brandDescription }}">
    @include('partials.assets')
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
    #brand-page-loader .brand-spinner{width:42px;height:42px;border:4px solid color-mix(in srgb, {{ \App\Models\Setting::get('brand_loader_color','#047857') }} 18%, transparent);border-top-color:{{ \App\Models\Setting::get('brand_loader_color','#047857') }};border-radius:50%;animation:brand-loader-spin .8s linear infinite}
    #brand-page-loader[data-style="dots"] .brand-spinner{width:10px;height:10px;border:0;border-radius:50%;background:{{ \App\Models\Setting::get('brand_loader_color','#047857') }};box-shadow:-16px 0 0 {{ \App\Models\Setting::get('brand_loader_color','#047857') }},16px 0 0 {{ \App\Models\Setting::get('brand_loader_color','#047857') }};animation:brand-loader-dots .8s ease-in-out infinite alternate}
    #brand-page-loader[data-style="pulse"] .brand-spinner{border:0;background:{{ \App\Models\Setting::get('brand_loader_color','#047857') }};animation:brand-loader-pulse 1s ease-in-out infinite}
    @keyframes brand-loader-spin{to{transform:rotate(360deg)}}
    @keyframes brand-loader-dots{to{transform:scale(1.25);opacity:.55}}
    @keyframes brand-loader-pulse{50%{transform:scale(.72);opacity:.55}}
    @media(prefers-reduced-motion:reduce){#brand-page-loader .brand-spinner{animation:none}}
</style>
@endif

@if ($brandLoaderEnabled)
<div id="brand-page-loader" role="status" aria-live="polite" aria-label="Loading website" data-style="{{ \App\Models\Setting::get('brand_loader_style','ring') }}">
    <div class="brand-loader-inner">
        <img class="brand-loader-logo" src="{{ \App\Models\Setting::get('logo') ? asset('storage/'.\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="">
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

<footer class="mt-16 border-t border-slate-200" style="background-color: {{ \App\Models\Setting::get('footer_bg', '#ffffff') }}; color: {{ \App\Models\Setting::get('footer_text', '#475569') }}">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 md:grid-cols-4">
        <div>
            <div class="flex items-center gap-2">
                <img src="{{ \App\Models\Setting::get('logo') ? asset('storage/'.\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="{{ $brandName }}" class="h-9 w-9 rounded-lg object-contain">
                <span class="font-extrabold text-slate-900">{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</span>
            </div>
            <p class="mt-3 text-sm leading-relaxed text-slate-500">{{ \App\Models\Setting::get('site_tagline', 'Quality education for everyone, everywhere.') }}</p>
        </div>
        <div>
            <h4 class="text-sm font-bold uppercase tracking-wide" style="color: {{ \App\Models\Setting::get('footer_heading', '#0f172a') }}">Learn</h4>
            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                <li><a class="hover:text-emerald-700" href="{{ route('courses.index') }}">All Courses</a></li>
                <li><a class="hover:text-emerald-700" href="{{ route('ebooks.index') }}">E-Books</a></li>
                <li><a class="hover:text-emerald-700" href="{{ route('student.exams.index') }}">Exams</a></li>
                <li><a class="hover:text-emerald-700" href="{{ route('certificates.verify', 'CERT-XXXXXXXX') }}">Verify Certificate</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-bold uppercase tracking-wide" style="color: {{ \App\Models\Setting::get('footer_heading', '#0f172a') }}">Company</h4>
            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                <li><a class="hover:text-emerald-700" href="{{ route('about') }}">About us</a></li>
                <li><a class="hover:text-emerald-700" href="{{ route('contact') }}">Contact</a></li>
                <li><a class="hover:text-emerald-700" href="{{ route('login') }}">Student login</a></li>
                @foreach (\App\Models\MenuItem::publicNavigation('footer', 'desktop') as $footerItem)
                    <li><a class="hover:text-emerald-700" href="{{ str_starts_with($footerItem->url, 'http') ? $footerItem->url : url($footerItem->url) }}" @if($footerItem->open_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $footerItem->label }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-bold uppercase tracking-wide" style="color: {{ \App\Models\Setting::get('footer_heading', '#0f172a') }}">Get in touch</h4>
            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                <li>{{ \App\Models\Setting::get('site_email', 'support@example.com') }}</li>
                <li>{{ \App\Models\Setting::get('site_phone', '+880 1700-000000') }}</li>
                @if (\App\Models\Setting::get('site_address'))
                    <li>{{ \App\Models\Setting::get('site_address') }}</li>
                @endif
            </ul>
            <div class="mt-4 flex flex-wrap gap-3 text-sm font-semibold">
                @foreach (['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram'] as $key => $label)
                    @if ($url = \App\Models\Setting::get($key))
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-emerald-700 hover:underline">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    <div class="border-t border-slate-100 py-4 text-center text-xs text-slate-500">
        © {{ date('Y') }} {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}. {{ \App\Models\Setting::get('footer_copyright', 'All rights reserved.') }}
    </div>
    @if (\App\Models\Setting::get('developer_show', '0') === '1' && \App\Models\Setting::get('developer_name'))
    <div class="border-t border-white/10 px-4 py-3 text-center text-xs" style="background-color: {{ \App\Models\Setting::get('developer_bg', '#0f172a') }}; color: {{ \App\Models\Setting::get('developer_text_color', '#ffffff') }}">
        {{ \App\Models\Setting::get('developer_text', 'Developed by') }}
        @if ($developerUrl = \App\Models\Setting::get('developer_url'))<a href="{{ $developerUrl }}" target="_blank" rel="noopener noreferrer" class="font-bold underline">{{ \App\Models\Setting::get('developer_name') }}</a>@else <strong>{{ \App\Models\Setting::get('developer_name') }}</strong>@endif
    </div>
    @endif
</footer>
@stack('scripts')
</body>
</html>
