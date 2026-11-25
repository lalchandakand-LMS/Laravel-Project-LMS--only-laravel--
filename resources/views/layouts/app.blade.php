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

@include('partials.public-navigation')

<main class="min-h-[70vh]">
    @include('partials.flash')
    @yield('content')
</main>

<footer class="mt-16 border-t border-slate-200 bg-white">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 md:grid-cols-4">
        <div>
            <div class="flex items-center gap-2">
                <img src="{{ \App\Models\Setting::get('logo') ? asset('storage/'.\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="{{ $brandName }}" class="h-9 w-9 rounded-lg object-contain">
                <span class="font-extrabold text-slate-900">{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</span>
            </div>
            <p class="mt-3 text-sm leading-relaxed text-slate-500">{{ \App\Models\Setting::get('site_tagline', 'Quality education for everyone, everywhere.') }}</p>
        </div>
        <div>
            <h4 class="text-sm font-bold uppercase tracking-wide text-slate-900">Learn</h4>
            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                <li><a class="hover:text-emerald-700" href="{{ route('courses.index') }}">All Courses</a></li>
                <li><a class="hover:text-emerald-700" href="{{ route('ebooks.index') }}">E-Books</a></li>
                <li><a class="hover:text-emerald-700" href="{{ route('student.exams.index') }}">Exams</a></li>
                <li><a class="hover:text-emerald-700" href="{{ route('certificates.verify', 'CERT-XXXXXXXX') }}">Verify Certificate</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-bold uppercase tracking-wide text-slate-900">Company</h4>
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
            <h4 class="text-sm font-bold uppercase tracking-wide text-slate-900">Get in touch</h4>
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
        © {{ date('Y') }} {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}. All rights reserved.
    </div>
</footer>
@stack('scripts')
</body>
</html>
