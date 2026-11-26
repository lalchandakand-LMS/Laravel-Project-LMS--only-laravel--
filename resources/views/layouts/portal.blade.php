<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal') — {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</title>
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
@php
    $user  = auth()->user();
    $role  = $user->role;
    $menus = [
        'instructor' => [
            ['teacher.dashboard',    'Dashboard',    '📊'],
            ['teacher.courses',      'My courses',   '📚'],
            ['teacher.students',     'My students',  '👩‍🎓'],
            ['teacher.live-classes', 'Live classes', '🎥'],
            ['teacher.leave-requests.index', 'Leave requests', '🗓'],
            ['teacher.assignments.index', 'Assignments', '📄'],
            ['ai-agent.index', 'AI Agents', '✨'],
            ['teacher.earnings',     'Earnings',     '💳'],
            ['notices.index', 'Notice board', '📣'],
            ['complaints.index', 'Complaints', '🛟']
        ],
        'guardian' => [
            ['guardian.dashboard', 'My children', '👨‍👩‍👦'],
            ['notices.index', 'Notice board', '📣'],
            ['complaints.index', 'Complaints', '🛟']
        ],
        'branch_manager' => [
            ['branch.dashboard', 'Dashboard',      '📊'],
            ['branch.students',  'Branch students','👩‍🎓'],
            ['branch.admissions.index', 'Admissions', '📝'],
            ['branch.staff',     'Teachers & staff','👥'],
            ['branch.attendance', 'Attendance review', '📅'],
            ['branch.finance', 'Finance & fee collection', '💰'],
            ['branch.revenue',   'Revenue ledger', '📈'],
            ['notices.index', 'Notice board', '📣'],
            ['complaints.index', 'Complaints', '🛟']
        ],
        'admin' => [
            ['admin.dashboard', 'Admin panel', '⚙️'],
            ['notices.index', 'Notice board', '📣'],
            ['complaints.index', 'Complaints', '🛟']
        ],
    ];
    $menu  = $menus[$role] ?? $menus['instructor'];
    $label = \App\Models\User::ROLES[$role] ?? ucfirst($role);
@endphp

<div class="flex min-h-screen">
    <aside class="role-sidebar sticky top-0 hidden h-screen w-64 shrink-0 flex-col p-4 text-slate-300 lg:flex">
        <a href="{{ $user->dashboardRoute() }}" class="role-brand flex items-center gap-3 px-2 py-3">
            <img src="{{ \App\Models\Setting::get('logo') ? media_url(\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="Alom's English Center" class="h-9 w-9 rounded-lg object-contain">
            <span class="font-extrabold text-white">{{ $label }} portal</span>
        </a>

        <nav class="mt-5 min-h-0 flex-1 space-y-1 overflow-y-auto pr-1 text-sm">
            @foreach ($menu as [$routeName, $text, $icon])
                <a href="{{ route($routeName) }}"
                   class="flex items-center gap-3 role-nav-link {{ request()->routeIs(str_replace(['.dashboard', '.index'], ['.*', '.*'], $routeName)) ? 'bg-blue-500/20 text-white ring-1 ring-orange-300/30' : 'hover:bg-white/10' }}">
                    <span>{{ $icon }}</span> {{ $text }}
                </a>
            @endforeach
        </nav>

        <div class="mt-4 rounded-xl bg-white/5 p-3 text-xs leading-relaxed text-slate-400">
            <p class="font-bold text-white">{{ $user->name }}</p>
            <p>{{ $label }}</p>
            @if ($user->branch)
                <p class="mt-1">🏢 {{ $user->branch->name }}</p>
            @endif
        </div>

        <div class="mt-auto space-y-0.5 border-t border-white/10 pt-3 text-sm">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/5">🌐 View site</a>
            <a href="{{ route('student.dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/5">🎒 My learning</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-rose-400 hover:bg-rose-500/10">🚪 Log out</button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="role-topbar sticky top-0 z-30 flex min-h-[68px] items-center gap-3 border-b border-slate-200 px-4 py-3 sm:px-6">
            <button type="button" id="mobile-nav-toggle" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 lg:hidden" aria-controls="mobile-portal-nav" aria-expanded="false">☰ <span class="sr-only">Open navigation</span></button>
            <h1 class="text-lg font-bold text-slate-900">@yield('heading', 'Dashboard')</h1>
            <div class="ml-auto flex items-center gap-3 text-sm">
                @yield('actions')
                @include('partials.communication-badges')
                <label for="admin-theme" class="sr-only">Color theme</label><select id="admin-theme" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700" aria-label="Color theme"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select>
                <img src="{{ $user->avatarUrl() }}" class="h-8 w-8 rounded-full" alt="">
                <span class="hidden font-semibold text-slate-700 sm:block">{{ $user->name }}</span>
            </div>
        </header>
        <nav id="mobile-portal-nav" class="hidden border-b border-slate-200 bg-white p-3 lg:hidden">
            @foreach ($menu as [$routeName, $text, $icon])
                <a href="{{ route($routeName) }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ $icon }} {{ $text }}</a>
            @endforeach
            <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700">🌐 View site</a>
            <a href="{{ route('student.dashboard') }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700">🎒 My learning</a>
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
