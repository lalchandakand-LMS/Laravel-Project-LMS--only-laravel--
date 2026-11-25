<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
    (function(){var t=localStorage.getItem('aec-admin-theme')||'system';var d=t==='dark'||(t==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.dataset.theme=d?'dark':'light';})();
    </script>
    <title>@yield('title', 'Admin') — {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</title>
    @include('partials.assets')
    <style>
      html[data-theme="dark"] body{background:#0b1220!important;color:#e2e8f0!important}
      html[data-theme="dark"] header,html[data-theme="dark"] main section,html[data-theme="dark"] main .bg-white{background-color:#111c2e!important;border-color:#263449!important;color:#e2e8f0}
      html[data-theme="dark"] main h1,html[data-theme="dark"] main h2,html[data-theme="dark"] main h3,html[data-theme="dark"] main p,html[data-theme="dark"] main label{color:inherit}
      html[data-theme="dark"] main input,html[data-theme="dark"] main textarea,html[data-theme="dark"] main select{background:#0b1220!important;color:#e2e8f0!important;border-color:#334155!important}
      html[data-theme="dark"] main .text-slate-900,html[data-theme="dark"] main .text-slate-800,html[data-theme="dark"] main .text-slate-700{color:#e2e8f0!important}
      html[data-theme="dark"] main .text-slate-600,html[data-theme="dark"] main .text-slate-500{color:#a8b5c7!important}
      html[data-theme="dark"] main .border-slate-100,html[data-theme="dark"] main .border-slate-200{border-color:#263449!important}
      html[data-theme="dark"] main .bg-slate-100{background:#1e293b!important}
      html[data-theme="dark"] main .divide-slate-100>*+*{border-color:#263449!important}
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen">
    <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col bg-slate-900 p-4 text-slate-300 lg:flex">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-2 py-2"><img src="{{ \App\Models\Setting::get('logo') ? asset('storage/'.\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg') }}" alt="Alom's English Center" class="h-9 w-9 rounded-lg object-contain"><span class="font-extrabold text-white">Admin panel</span></a>
        @php
            $isAdmin = auth()->user()->isAdmin();
            $groups = [
                'Content' => [['admin.courses.index', 'Courses', '📚'],['admin.categories.index', 'Categories', '🗂'],['admin.exams.index', 'Exams', '📝'],['admin.ebooks.index', 'E-books', '📖'],['admin.live-classes.index', 'Live classes', '🎥'],['admin.announcements.index', 'Announcements', '📣']],
                'People' => array_values(array_filter([['admin.enrollments.index', 'Enrollments', '🎓'],['admin.users.index', 'Students & staff', '👥'],$isAdmin ? ['admin.branches.index', 'Branches', '🏢'] : null,['admin.reviews.index', 'Reviews', '⭐']])),
                'Commerce' => [['admin.orders.index', 'Orders', '🧾'],['admin.coupons.index', 'Coupons', '🎟']],
                'System' => array_values(array_filter([$isAdmin ? ['admin.sliders.index', 'Home slider', '🖼'] : null,$isAdmin ? ['admin.settings.index', 'Settings', '⚙️'] : null,$isAdmin ? ['admin.menu-settings.index', 'Menu Settings', '🧭'] : null])),
            ];
        @endphp
        <nav class="mt-4 min-h-0 flex-1 space-y-2 overflow-y-auto pr-1 text-sm">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 font-semibold {{ request()->routeIs('admin.dashboard') ? 'bg-emerald-500 text-slate-900' : 'hover:bg-white/10' }}">📊 Dashboard</a>
            @foreach ($groups as $group => $items)<div><p class="px-3 pt-1 text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $group }}</p><div class="mt-1 space-y-0.5">@foreach ($items as [$route, $label, $icon])<a href="{{ route($route) }}" class="flex items-center gap-3 rounded-lg px-3 py-1.5 {{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'bg-white/10 text-white' : 'hover:bg-white/5' }}"><span>{{ $icon }}</span> {{ $label }}</a>@endforeach</div></div>@endforeach
        </nav>
        <div class="mt-auto shrink-0 border-t border-white/10 pt-3 text-sm"><a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg px-3 py-1 hover:bg-white/5">🌐 View site</a><a href="{{ route('student.dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-1 hover:bg-white/5">🎒 Student view</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="flex w-full items-center gap-3 rounded-lg px-3 py-1 text-rose-400 hover:bg-rose-500/10">🚪 Log out</button></form></div>
    </aside>
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
            <h1 class="text-lg font-bold text-slate-900">@yield('heading', 'Dashboard')</h1>
            <div class="ml-auto flex items-center gap-3 text-sm">
                @yield('actions')
                <label for="admin-theme" class="sr-only">Admin theme</label><select id="admin-theme" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700" aria-label="Color theme"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select>
                <img src="{{ auth()->user()->avatarUrl() }}" class="h-8 w-8 rounded-full" alt=""><span class="hidden font-semibold text-slate-700 sm:block">{{ auth()->user()->name }}</span>
            </div>
        </header>
        <main class="flex-1 p-4 sm:p-6">@include('partials.flash')@yield('content')</main>
    </div>
</div>
<script>
(function(){
 const select=document.getElementById('admin-theme'); if(!select)return;
 const saved=localStorage.getItem('aec-admin-theme')||'system';select.value=saved;
 function apply(value){const dark=value==='dark'||(value==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.dataset.theme=dark?'dark':'light';}
 select.addEventListener('change',function(){localStorage.setItem('aec-admin-theme',this.value);apply(this.value);});
 if(saved==='system'&&window.matchMedia){window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>apply('system'));}
})();
</script>
@stack('scripts')
</body>
</html>
