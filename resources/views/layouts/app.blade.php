<!DOCTYPE html>
<html lang="{{ session('locale', 'en') }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($brandName = \App\Models\Setting::get('site_name', "Alom's English Center"))
    @php($pageTitle = html_entity_decode(trim($__env->yieldContent('meta_title', $__env->yieldContent('title', ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
    @php($seoTitle = $pageTitle !== '' ? $pageTitle.' — '.$brandName : \App\Models\Setting::get('seo_title', $brandName))
    @php($brandDescription = trim(strip_tags($__env->yieldContent('meta_description', \App\Models\Setting::get('seo_description', \App\Models\Setting::get('site_tagline', 'Practical English learning, speaking practice, grammar and vocabulary.'))))))
    @php($privateSeoRoute = auth()->check() || request()->is('admin', 'admin/*', 'my', 'my/*', 'teacher', 'teacher/*', 'guardian', 'guardian/*', 'branch', 'branch/*', 'cart', 'checkout', 'checkout/*', 'messages', 'messages/*', 'notifications', 'notifications/*'))
    @php($seoIndexEnabled = \App\Models\Setting::get('seo_robots_index', '1') === '1')
    @php($robotsDirective = $privateSeoRoute || !$seoIndexEnabled ? 'noindex, nofollow' : $__env->yieldContent('meta_robots', 'index, follow'))
    @php($canonicalUrl = $__env->yieldContent('canonical_url', url()->current()))
    @php($socialImage = trim($__env->yieldContent('meta_image', '')) ?: (\App\Models\Setting::get('seo_default_image') ?: media_url(setting('logo'), asset('images/aec-logo.svg'))))
    @php($socialType = trim($__env->yieldContent('og_type', 'website')) ?: 'website')
    <meta name="robots" content="{{ $robotsDirective }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta property="og:type" content="{{ $socialType }}">
    <meta property="og:site_name" content="{{ $brandName }}">
    <meta property="og:title" content="{{ $seoTitle !== '' ? $seoTitle : trim($__env->yieldContent('title', $brandName).' — '.$brandName) }}">
    <meta property="og:description" content="{{ $brandDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:alt" content="{{ trim($__env->yieldContent('meta_image_alt', $seoTitle)) }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle !== '' ? $seoTitle : trim($__env->yieldContent('title', $brandName).' — '.$brandName) }}">
    <meta name="twitter:description" content="{{ $brandDescription }}">
    <title>{{ $seoTitle !== '' ? $seoTitle : trim($__env->yieldContent('title', $brandName).' — '.$brandName) }}</title>
    <meta name="description" content="{{ $brandDescription }}">
    @if (filled(\App\Models\Setting::get('google_site_verification')) && !$privateSeoRoute)
        <meta name="google-site-verification" content="{{ \App\Models\Setting::get('google_site_verification') }}">
    @endif
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'EducationalOrganization',
                '@id' => rtrim(config('app.url'), '/').'#organization',
                'name' => $brandName,
                'url' => config('app.url'),
                'logo' => $socialImage,
                'description' => $brandDescription,
                'contactPoint' => array_filter([
                    '@type' => 'ContactPoint',
                    'telephone' => \App\Models\Setting::get('site_phone') ?: null,
                    'email' => \App\Models\Setting::get('site_email') ?: null,
                    'contactType' => 'customer support',
                ]),
                'sameAs' => array_values(array_filter([
                    \App\Models\Setting::get('facebook'),
                    \App\Models\Setting::get('youtube'),
                    \App\Models\Setting::get('instagram'),
                ])),
            ],
            [
                '@type' => 'WebSite',
                '@id' => rtrim(config('app.url'), '/').'#website',
                'url' => config('app.url'),
                'name' => $brandName,
                'publisher' => ['@id' => rtrim(config('app.url'), '/').'#organization'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => route('courses.index').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    @stack('structured_data')
    @include('partials.assets')
    <style>
        :root {
            --aec-primary: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', (string) setting('site_primary_color')) ? setting('site_primary_color') : '#1cab55' }};
            --aec-primary-dark: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', (string) setting('site_secondary_color')) ? setting('site_secondary_color') : '#15803d' }};
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased" data-public-motion="{{ \App\Models\Setting::get('public_page_motion', 'soft') }}">

@php($brandLoaderEnabled = \App\Models\Setting::get('brand_loader_enabled', '0') === '1')
@if ($brandLoaderEnabled)
<style>
    #brand-page-loader{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:{{ \App\Models\Setting::get('brand_loader_bg','#ffffff') }};opacity:1;visibility:visible;transition:opacity .25s ease,visibility .25s ease}
    #brand-page-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
    #brand-page-loader .brand-loader-inner{display:flex;flex-direction:column;align-items:center;gap:16px;text-align:center;padding:24px}
    #brand-page-loader .brand-loader-logo{width:76px;height:76px;object-fit:contain;border-radius:16px}
    #brand-page-loader .brand-loader-name{font-weight:800;font-size:1.15rem;color:{{ \App\Models\Setting::get('brand_loader_text_color','#0f172a') }}}
    #brand-page-loader .brand-loader-message{font-size:.9rem;color:{{ \App\Models\Setting::get('brand_loader_text_color','#0f172a') }}}
    #brand-page-loader .brand-spinner{width:42px;height:42px;border:4px solid color-mix(in srgb, {{ (strtolower((string) \App\Models\Setting::get('brand_loader_color', '#1cab55')) === '#1cab55' ? '#1cab55' : \App\Models\Setting::get('brand_loader_color', '#1cab55')) }} 18%, transparent);border-top-color:{{ (strtolower((string) \App\Models\Setting::get('brand_loader_color', '#1cab55')) === '#1cab55' ? '#1cab55' : \App\Models\Setting::get('brand_loader_color', '#1cab55')) }};border-radius:50%;animation:brand-loader-spin .8s linear infinite}
    #brand-page-loader[data-style="dots"] .brand-spinner{width:10px;height:10px;border:0;border-radius:50%;background:{{ (strtolower((string) \App\Models\Setting::get('brand_loader_color', '#1cab55')) === '#1cab55' ? '#1cab55' : \App\Models\Setting::get('brand_loader_color', '#1cab55')) }};box-shadow:-16px 0 0 {{ (strtolower((string) \App\Models\Setting::get('brand_loader_color', '#1cab55')) === '#1cab55' ? '#1cab55' : \App\Models\Setting::get('brand_loader_color', '#1cab55')) }},16px 0 0 {{ (strtolower((string) \App\Models\Setting::get('brand_loader_color', '#1cab55')) === '#1cab55' ? '#1cab55' : \App\Models\Setting::get('brand_loader_color', '#1cab55')) }};animation:brand-loader-dots .8s ease-in-out infinite alternate}
    #brand-page-loader[data-style="pulse"] .brand-spinner{border:0;background:{{ (strtolower((string) \App\Models\Setting::get('brand_loader_color', '#1cab55')) === '#1cab55' ? '#1cab55' : \App\Models\Setting::get('brand_loader_color', '#1cab55')) }};animation:brand-loader-pulse 1s ease-in-out infinite}
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
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}" class="grid h-10 w-10 place-items-center rounded-full border border-white/15 bg-white/5 text-sm font-extrabold text-emerald-200 transition hover:bg-emerald-500 hover:text-slate-950">{{ $symbol }}</a>
                    @endif
                @endforeach
            </div>
        </div>
        <div>
            <h4 class="text-sm font-extrabold uppercase tracking-wider text-white">Links</h4>
            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                <li><a class="hover:text-emerald-300" href="{{ route('courses.index') }}">All Courses</a></li>
                <li><a class="hover:text-emerald-300" href="{{ route('blog.index') }}">Blog & News</a></li>
                <li><a class="hover:text-emerald-300" href="{{ route('coaching-admission.create') }}">Coaching Admission</a></li>
                <li><a class="hover:text-emerald-300" href="{{ route('ebooks.index') }}">E-Books</a></li>
                <li><a class="hover:text-emerald-300" href="{{ route('about') }}">About us</a></li>
                @foreach (\App\Models\MenuItem::publicNavigation('footer', 'desktop') as $footerItem)
                    <li><a class="hover:text-emerald-300" href="{{ str_starts_with($footerItem->url, 'http') ? $footerItem->url : url($footerItem->url) }}" @if($footerItem->open_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $footerItem->label }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-extrabold uppercase tracking-wider text-white">Company Details</h4>
            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                <li>{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</li>
                @if (\App\Models\Setting::get('company_license'))<li>License: {{ \App\Models\Setting::get('company_license') }}</li>@endif
                @if (\App\Models\Setting::get('site_address'))<li>{{ \App\Models\Setting::get('site_address') }}</li>@endif
                <li><a class="hover:text-emerald-300" href="{{ route('contact') }}">Contact company</a></li>
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
            <div class="flex flex-wrap items-center justify-center gap-2" aria-label="Accepted payment methods">
                @foreach (['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'bank' => 'Bank transfer'] as $method => $label)
                    @if (isset($branch) && $branch
                        ? in_array($method, $branch->enabledStudentPaymentMethods(), true)
                        : \App\Models\Branch::active()->get()->contains(fn ($paymentBranch) => in_array($method, $paymentBranch->enabledStudentPaymentMethods(), true)))
                        <span class="rounded-md border border-white/15 bg-white px-3 py-2 text-xs font-extrabold {{ $method === 'bkash' ? 'text-pink-600' : ($method === 'nagad' ? 'text-orange-600' : 'text-slate-800') }}">{{ $label }}</span>
                    @endif
                @endforeach
                <span class="rounded-md border border-white/15 bg-white px-3 py-2 text-xs font-black italic text-blue-900">VISA <small class="ml-1 font-medium not-italic text-slate-500">Coming soon</small></span>
                <span class="rounded-md border border-white/15 bg-white px-3 py-2 text-xs font-black text-slate-800">Mastercard <small class="ml-1 font-medium text-slate-500">Coming soon</small></span>
                <span class="rounded-md border border-white/15 bg-white px-3 py-2 text-xs font-black text-emerald-700">SSLCommerz <small class="ml-1 font-medium text-slate-500">Coming soon</small></span>
            </div>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-xs text-slate-400">
            <span>© {{ date('Y') }} {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}. {{ \App\Models\Setting::get('footer_copyright', 'All rights reserved.') }}</span>
            <nav aria-label="Legal policies" class="flex flex-wrap gap-x-4 gap-y-2"><a href="{{ url('/terms-and-conditions') }}" class="hover:text-emerald-300">Terms & Conditions</a><a href="{{ url('/privacy-policy') }}" class="hover:text-emerald-300">Privacy Policy</a><a href="{{ url('/refund-policy') }}" class="hover:text-emerald-300">Refund Policy</a>@if((!$privateSeoRoute || request()->routeIs('checkout.success')) && (filled(\App\Models\Setting::get('ga_measurement_id')) || filled(\App\Models\Setting::get('meta_pixel_id')) || filled(\App\Models\Setting::get('gtm_container_id'))))<button type="button" onclick="window.aecOpenCookieSettings && window.aecOpenCookieSettings()" class="hover:text-emerald-300 underline">Cookie settings</button>@endif</nav>
        </div>
    </div>
    @if (\App\Models\Setting::get('developer_show', '0') === '1' && \App\Models\Setting::get('developer_name'))
    <div class="border-t border-white/10 px-4 py-3 text-center text-xs" style="background-color: {{ \App\Models\Setting::get('developer_bg', '#0f172a') }}; color: {{ \App\Models\Setting::get('developer_text_color', '#ffffff') }}">{{ \App\Models\Setting::get('developer_text', 'Developed by') }} @if ($developerUrl = \App\Models\Setting::get('developer_url'))<a href="{{ $developerUrl }}" target="_blank" rel="noopener noreferrer" class="font-bold underline">{{ \App\Models\Setting::get('developer_name') }}</a>@else <strong>{{ \App\Models\Setting::get('developer_name') }}</strong>@endif</div>
    @endif
</footer>
@if (!$privateSeoRoute || request()->routeIs('checkout.success'))
    @if (session('aec_lead_event'))
        <script>window.aecLeadEvent = @json(session('aec_lead_event'));</script>
    @endif
    @include('partials.cookie-consent')
@endif
@include('partials.marketing-popup')
@stack('scripts')
</body>
</html>
