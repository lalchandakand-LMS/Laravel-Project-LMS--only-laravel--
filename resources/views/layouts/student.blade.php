<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</title>
    @include('partials.assets')
    <script>(function(){var t=localStorage.getItem('aec-admin-theme')||'system';document.documentElement.dataset.theme=(t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))?'dark':'light';})();</script>
    <style>
      html[data-theme="dark"] body{background:#0b1220!important;color:#e2e8f0!important}
      html[data-theme="dark"] header,html[data-theme="dark"] main section,html[data-theme="dark"] main .bg-white{background-color:#111c2e!important;border-color:#263449!important;color:#e2e8f0}
      html[data-theme="dark"] main h1,html[data-theme="dark"] main h2,html[data-theme="dark"] main h3,html[data-theme="dark"] main p,html[data-theme="dark"] main label{color:inherit}
      html[data-theme="dark"] main input,html[data-theme="dark"] main textarea,html[data-theme="dark"] main select{background:#0b1220!important;color:#e2e8f0!important;border-color:#334155!important}
      html[data-theme="dark"] main .text-slate-900,html[data-theme="dark"] main .text-slate-800,html[data-theme="dark"] main .text-slate-700,html[data-theme="dark"] main .text-slate-600{color:#e2e8f0!important}
      html[data-theme="dark"] main .text-slate-500{color:#a8b5c7!important}
      html[data-theme="dark"] main .border-slate-100,html[data-theme="dark"] main .border-slate-200{border-color:#263449!important}
      html[data-theme="dark"] main .bg-slate-50,html[data-theme="dark"] main .bg-slate-100{background:#1e293b!important}
      html[data-theme="dark"] main .divide-slate-100>*+*{border-color:#263449!important}

      body{background:radial-gradient(ellipse at 8% 0%,rgba(219,234,254,.72),transparent 34%),#f1f5f9!important}
      .role-sidebar{background:linear-gradient(165deg,#071b3d 0%,#0a2d5a 48%,#07172f 100%);box-shadow:12px 0 40px rgba(2,12,34,.16)}
      .role-brand{border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.06);border-radius:16px;padding:10px}
      .role-nav-link{display:flex;align-items:center;gap:.8rem;border:1px solid transparent;border-radius:13px;padding:.72rem .85rem;color:#cbdcf2;font-size:.84rem;font-weight:700;transition:all .18s ease}
      .role-nav-link:hover{background:rgba(125,211,252,.12);color:#fff;transform:translateX(2px)}
      .role-nav-link[aria-current="page"]{background:linear-gradient(90deg,rgba(249,115,22,.28),rgba(59,130,246,.15));color:#fff;border-color:rgba(251,146,60,.18);box-shadow:inset 3px 0 #fb923c}
      .role-topbar{background:rgba(255,255,255,.88)!important;backdrop-filter:blur(18px);box-shadow:0 8px 28px rgba(15,23,42,.05)}
      .role-main{max-width:1680px;width:100%;margin-inline:auto}
      .role-mobile-nav{box-shadow:0 12px 24px rgba(15,23,42,.06)}
      .role-mobile-nav a{border-radius:10px}
      .role-sidebar .text-rose-400{color:#fda4af}
      .role-main .rounded-2xl.border,.role-main .rounded-xl.border{box-shadow:0 8px 26px rgba(15,23,42,.045);transition:box-shadow .18s ease,border-color .18s ease}
      .role-main .rounded-2xl.border:hover{box-shadow:0 12px 30px rgba(15,23,42,.075)}
      .role-main a:focus-visible,.role-sidebar a:focus-visible,button:focus-visible,select:focus-visible{outline:3px solid rgba(249,115,22,.55);outline-offset:2px}
      html[data-theme="dark"] body{background:radial-gradient(ellipse at 8% 0%,rgba(30,64,115,.25),transparent 35%),#071326!important}
      html[data-theme="dark"] .role-topbar{background:rgba(16,31,53,.9)!important;border-color:#263b57!important}
      html[data-theme="dark"] .role-main .rounded-2xl.border,html[data-theme="dark"] .role-main .rounded-xl.border{box-shadow:0 8px 26px rgba(0,0,0,.12)}
      @media(max-width:1023px){.role-main{padding:1rem!important}}
    </style>
</head>
<body class="role-dashboard bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen">
    {{-- SIDEBAR --}}
    <aside class="role-sidebar sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-white/10 p-4 text-slate-300 lg:flex">
        <a href="{{ route('home') }}" class="role-brand flex items-center gap-3 px-2 py-3">
            <img src="{{ \App\Models\Setting::get('logo') ? media_url(\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="Alom's English Center" class="h-9 w-9 rounded-lg object-contain">
            <span class="font-extrabold text-white">{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</span>
        </a>

        @php
            $nav = [
                ['student.dashboard', 'Dashboard', '🏠'],
                ['student.courses', 'My courses', '📚'],
                ['student.live-classes', 'Live classes', '🎥'],
                ['student.assignments.index', 'Assignments', '📄'],
                ['student.library.index', 'Library', '📚'],
                ['ai-agent.index', 'AI Study Assistant', '✨'],
                ['student.online-batches', 'Online live batches', '💻'],
                ['coaching-fees.student', 'Coaching fees', '💰'],
                ['student.exams.index', 'Exams', '📝'],
                ['student.ebooks', 'E-books', '📖'],
                ['student.certificates', 'Certificates', '🏅'],
                ['student.orders', 'Orders', '🧾'],
                ['student.profile', 'Profile', '👤'],
                ['notices.index', 'Notice board', '📣'],
                ['complaints.index', 'Complaints', '🛟']
            ];
        @endphp

        <nav class="mt-6 min-h-0 flex-1 space-y-1 overflow-y-auto pr-1 text-sm font-medium">
            @foreach ($nav as [$route, $label, $icon])
                <a href="{{ route($route) }}"
                   class="role-nav-link" @if(request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route)) aria-current="page" @endif>
                    <span>{{ $icon }}</span> {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="mt-auto space-y-1 border-t border-slate-100 pt-4 text-sm">
            @if (auth()->user()->isAdmin() || auth()->user()->isInstructor())
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 role-nav-link text-slate-300 hover:bg-white/10">⚙️ Admin panel</a>
            @endif
            <a href="{{ route('courses.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-slate-300 hover:bg-white/10">🔎 Browse courses</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-rose-600 hover:bg-rose-50">🚪 Log out</button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="role-topbar sticky top-0 z-30 flex min-h-[68px] items-center gap-3 border-b border-slate-200 px-4 py-3 sm:px-6">
            <button type="button" id="mobile-nav-toggle" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 lg:hidden" aria-controls="mobile-portal-nav" aria-expanded="false">☰ <span class="sr-only">Open navigation</span></button>
            <a href="{{ route('student.dashboard') }}" class="font-extrabold text-slate-900 lg:hidden">AEC</a>
            <h1 class="text-lg font-bold text-slate-900">@yield('heading', 'Dashboard')</h1>
            <div class="ml-auto flex items-center gap-3">
                @include('partials.communication-badges')
                <a href="{{ route('cart.index') }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">🛒</a>
                <div class="flex items-center gap-2">
                    <img src="{{ auth()->user()->avatarUrl() }}" class="h-8 w-8 rounded-full object-cover" alt="">
                    <div class="hidden text-xs sm:block">
                        <p class="font-bold text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="text-slate-500">{{ auth()->user()->email }}</p>
                    </div>
                </div>
            </div>
        </header>

        <nav id="mobile-portal-nav" class="role-mobile-nav hidden border-b border-slate-200 bg-white p-3 lg:hidden">
            @foreach ($nav as [$route, $label, $icon])
                <a href="{{ route($route) }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ $icon }} {{ $label }}</a>
            @endforeach
            <a href="{{ route('courses.index') }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700">🔎 Browse courses</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-rose-600">🚪 Log out</button></form>
        </nav>
        <main class="role-main flex-1 p-4 sm:p-6 lg:p-7">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<script>(function(){const b=document.getElementById('mobile-nav-toggle'),n=document.getElementById('mobile-portal-nav');if(b&&n)b.addEventListener('click',()=>{const open=n.classList.toggle('hidden')===false;b.setAttribute('aria-expanded',String(open));});})();</script>
<script>(function(){const s=document.getElementById('admin-theme');if(!s)return;const v=localStorage.getItem('aec-admin-theme')||'system';s.value=v;function apply(x){document.documentElement.dataset.theme=(x==='dark'||(x==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))?'dark':'light';}s.addEventListener('change',()=>{localStorage.setItem('aec-admin-theme',s.value);apply(s.value);});if(v==='system'&&matchMedia){matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>apply('system'));}})();</script>
</body>
</html>
