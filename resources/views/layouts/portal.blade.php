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
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
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
    <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col bg-slate-900 p-4 text-slate-300 lg:flex">
        <a href="{{ $user->dashboardRoute() }}" class="flex items-center gap-2 px-2 py-2">
            <img src="{{ \App\Models\Setting::get('logo') ? media_url(\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="Alom's English Center" class="h-9 w-9 rounded-lg object-contain">
            <span class="font-extrabold text-white">{{ $label }} portal</span>
        </a>

        <nav class="mt-5 space-y-1 text-sm">
            @foreach ($menu as [$routeName, $text, $icon])
                <a href="{{ route($routeName) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-semibold {{ request()->routeIs(str_replace(['.dashboard', '.index'], ['.*', '.*'], $routeName)) ? 'bg-emerald-500 text-slate-900' : 'hover:bg-white/10' }}">
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
        <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
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
        <main class="flex-1 p-4 sm:p-6">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<script>(function(){const b=document.getElementById('mobile-nav-toggle'),n=document.getElementById('mobile-portal-nav');if(b&&n)b.addEventListener('click',()=>{const open=n.classList.toggle('hidden')===false;b.setAttribute('aria-expanded',String(open));});})();</script>
<script>(function(){const s=document.getElementById('admin-theme');if(!s)return;const v=localStorage.getItem('aec-admin-theme')||'system';s.value=v;function apply(x){document.documentElement.dataset.theme=(x==='dark'||(x==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))?'dark':'light';}s.addEventListener('change',()=>{localStorage.setItem('aec-admin-theme',s.value);apply(s.value);});if(v==='system'&&matchMedia){matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>apply('system'));}})();</script>
</body>
</html>
