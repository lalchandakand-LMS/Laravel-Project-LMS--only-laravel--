<!DOCTYPE html>
<html lang="en" data-admin-preset="{{ \App\Models\Setting::get('admin_theme_preset', 'navy') }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <script>(function(){var t=localStorage.getItem('aec-admin-theme')||'system';var d=t==='dark'||(t==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.dataset.theme=d?'dark':'light';})();</script>
    <title>@yield('title', 'Admin') — {{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</title>
    @include('partials.assets')
    <style>
      html[data-theme="dark"] body{background:#071326!important;color:#e2e8f0!important}
      html[data-theme="dark"] header,html[data-theme="dark"] main section,html[data-theme="dark"] main .bg-white{background-color:#101f35!important;border-color:#263b57!important;color:#e2e8f0}
      html[data-theme="dark"] main h1,html[data-theme="dark"] main h2,html[data-theme="dark"] main h3,html[data-theme="dark"] main p,html[data-theme="dark"] main label{color:inherit}
      html[data-theme="dark"] main input,html[data-theme="dark"] main textarea,html[data-theme="dark"] main select{background:#071326!important;color:#e2e8f0!important;border-color:#334b68!important}
      html[data-theme="dark"] main .text-slate-900,html[data-theme="dark"] main .text-slate-800,html[data-theme="dark"] main .text-slate-700{color:#e2e8f0!important}
      html[data-theme="dark"] main .text-slate-600,html[data-theme="dark"] main .text-slate-500{color:#a8b9d0!important}
      html[data-theme="dark"] main .border-slate-100,html[data-theme="dark"] main .border-slate-200{border-color:#263b57!important}
      html[data-theme="dark"] main .bg-slate-100{background:#1e314c!important}
      #admin-sidebar{background:linear-gradient(165deg,#071b3d 0%,#0a2d5a 48%,#07172f 100%);box-shadow:12px 0 40px rgba(2,12,34,.16)}
      #admin-sidebar .nav-link{transition:background .18s ease,color .18s ease,transform .18s ease}
      #admin-sidebar .nav-link:hover{background:rgba(125,211,252,.12);color:white;transform:translateX(2px)}
      #admin-sidebar .nav-link[aria-current="page"]{background:linear-gradient(90deg,rgba(14,165,233,.28),rgba(59,130,246,.12));color:white;box-shadow:inset 3px 0 #38bdf8}
      @media(min-width:1024px){#admin-sidebar[data-collapsed="true"]{width:5.25rem}#admin-sidebar[data-collapsed="true"] .sidebar-label,#admin-sidebar[data-collapsed="true"] .sidebar-group,#admin-sidebar[data-collapsed="true"] .sidebar-promo,#admin-sidebar[data-collapsed="true"] .sidebar-version{display:none}#admin-sidebar[data-collapsed="true"] .nav-link{justify-content:center;padding-left:.7rem;padding-right:.7rem}#admin-sidebar[data-collapsed="true"] .nav-icon{font-size:1.2rem}}
      :root{--admin-card-radius:{{ (int) \App\Models\Setting::get('admin_card_radius', 18) }}px}
      body main .rounded-2xl{border-radius:var(--admin-card-radius)!important}
      html[data-admin-preset="orange"] #admin-sidebar{background:linear-gradient(165deg,#431407 0%,#9a3412 48%,#431407 100%)}
      html[data-admin-preset="orange"] #admin-sidebar .nav-link[aria-current="page"]{background:linear-gradient(90deg,rgba(251,146,60,.32),rgba(249,115,22,.12));box-shadow:inset 3px 0 #fb923c}
      html[data-admin-preset="teal"] #admin-sidebar{background:linear-gradient(165deg,#042f2e 0%,#0f766e 48%,#042f2e 100%)}
      html[data-admin-preset="teal"] #admin-sidebar .nav-link[aria-current="page"]{background:linear-gradient(90deg,rgba(45,212,191,.28),rgba(20,184,166,.12));box-shadow:inset 3px 0 #2dd4bf}
      html[data-admin-preset="purple"] #admin-sidebar{background:linear-gradient(165deg,#2e1065 0%,#6b21a8 48%,#2e1065 100%)}
      html[data-admin-preset="purple"] #admin-sidebar .nav-link[aria-current="page"]{background:linear-gradient(90deg,rgba(192,132,252,.28),rgba(168,85,247,.12));box-shadow:inset 3px 0 #c084fc}
      #admin-search-results{max-height:360px;overflow:auto}
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen">
    <div id="sidebar-scrim" class="fixed inset-0 z-40 hidden bg-slate-950/60 lg:hidden"></div>
    <aside id="admin-sidebar" data-collapsed="false" class="fixed inset-y-0 left-0 z-50 flex h-screen w-72 -translate-x-full flex-col border-r border-white/10 p-4 text-slate-200 transition-transform duration-200 lg:sticky lg:top-0 lg:w-64 lg:translate-x-0">
        <div class="flex items-center justify-between gap-2 px-1 py-2">
            <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-3">
                <img src="{{ \App\Models\Setting::get('admin_logo') ? media_url(\App\Models\Setting::get('admin_logo')) : (\App\Models\Setting::get('logo') ? media_url(\App\Models\Setting::get('logo')) : asset('images/aec-logo.svg')) }}" alt="{{ \App\Models\Setting::get('admin_brand_title', 'Admin workspace') }}" class="h-10 w-10 shrink-0 rounded-xl bg-white/95 p-1 object-contain shadow-lg">
                <span class="sidebar-label min-w-0"><b class="block truncate text-sm font-black tracking-wide text-white">{{ \App\Models\Setting::get('admin_brand_title', \App\Models\Setting::get('site_name', "Alom's LMS")) }}</b><span class="block text-[10px] font-medium text-blue-200">{{ \App\Models\Setting::get('admin_brand_subtitle', 'Admin workspace') }}</span></span>
            </a>
            <span class="sidebar-version rounded-full border border-cyan-300/30 bg-cyan-300/10 px-2 py-1 text-[9px] font-black tracking-wider text-cyan-200">v3.0</span>
        </div>
        <div class="sidebar-label mx-1 mt-4 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-[10px] font-bold uppercase tracking-[.18em] text-blue-200">Workspace navigation</div>
        <nav class="mt-3 min-h-0 flex-1 space-y-1 overflow-y-auto pr-1 text-sm">
            @php($menu = [
                ['admin.dashboard','Dashboard','▦','admin.dashboard'],
                ['admin.users.index','Students','♟','admin.users.*','role=student'],
                ['admin.users.index','Teachers','✦','admin.users.*','role=instructor'],
                ['admin.courses.index','Courses','▣','admin.courses.*'],
                ['admin.batches.index','Batches','▤','admin.batches.*'],
                ['admin.exams.index','Exams','✎','admin.exams.*'],
                ['admin.attendance.index','Attendance','◷','admin.attendance.*'],
                ['admin.orders.index','Payments','৳','admin.orders.*'],
                ['admin.products.index','Physical Products','▣','admin.products.*'],
                ['admin.certificates.index','Certificates','✧','admin.certificates.*'],
                ['admin.reports.index','Reports','▥','admin.reports.*'],
                ['messages.index','Messages','✉','messages.*'],
                ['admin.marketing.dashboard','Marketing Dashboard','▦','admin.marketing.dashboard'],
                ['admin.marketing.leads.index','Marketing Leads','♧','admin.marketing.leads.*'],
                ['admin.marketing.campaigns.index','Campaigns','◉','admin.marketing.campaigns.*'],
                ['admin.marketing.newsletter.index','Newsletter','✉','admin.marketing.newsletter.*'],
                ['admin.marketing.popup.edit','Marketing Popup','▣','admin.marketing.popup.*'],
                ['admin.seo.index','SEO & Marketing','⌕','admin.seo.*'],
                ['admin.seo.redirects.index','SEO Redirects','↪','admin.seo.redirects.*'],
                ['admin.settings.index','Settings','⚙','admin.settings.*'],
            ])
            @foreach($menu as $item)
                @php($routeName = $item[0])
                @if(in_array($routeName, ['admin.seo.index', 'admin.seo.redirects.index', 'admin.marketing.dashboard', 'admin.marketing.leads.index', 'admin.marketing.campaigns.index', 'admin.marketing.newsletter.index', 'admin.marketing.popup.edit'], true) && auth()->user()->role !== 'admin') @continue @endif
                @php($routePattern = $item[3])
                @php($isActive = request()->routeIs($routePattern) || ($routeName === 'admin.users.index' && request()->routeIs('admin.users.*') && request('role') === str_replace('role=', '', ($item[4] ?? ''))))
                <a href="{{ route($routeName, isset($item[4]) ? ['role' => str_replace('role=', '', $item[4])] : []) }}" class="nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold {{ $isActive ? 'text-white' : 'text-blue-100' }}" @if($isActive) aria-current="page" @endif>
                    <span class="nav-icon grid h-6 w-6 shrink-0 place-items-center text-lg">{{ $item[2] }}</span>
                    <span class="sidebar-label flex-1">{{ $item[1] }}</span>
                    @if($item[1] === 'Messages')
                        <span data-unread-badge="messages" class="sidebar-label {{ auth()->user()->receivedMessages()->whereNull('read_at')->count() ? '' : 'hidden' }} rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-black text-white">{{ auth()->user()->receivedMessages()->whereNull('read_at')->count() }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
        @if (\App\Models\Setting::get('admin_sidebar_promo_enabled', '1') === '1')
        <div class="sidebar-promo mt-4 rounded-2xl border border-cyan-300/20 bg-gradient-to-br from-blue-500/25 to-cyan-400/10 p-4 shadow-lg">
            <div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-cyan-300/15 text-lg text-cyan-200">✦</span><span class="text-xs font-extrabold text-white">{{ \App\Models\Setting::get('admin_sidebar_promo_title', 'Grow your academy') }}</span></div>
            <p class="mt-2 text-[11px] leading-5 text-blue-100/80">{{ \App\Models\Setting::get('admin_sidebar_promo_body', 'Keep your courses, learners and live classes moving forward.') }}</p>
            <a href="{{ \App\Models\Setting::get('admin_sidebar_promo_url', route('admin.settings.index')) }}" class="mt-3 inline-flex rounded-lg bg-cyan-300 px-3 py-2 text-[10px] font-black text-blue-950 hover:bg-cyan-200">{{ \App\Models\Setting::get('admin_sidebar_promo_button', 'Explore settings') }} →</a>
        </div>
        @endif
        <div class="mt-4 border-t border-white/10 pt-3">
            <a href="{{ route('home') }}" class="nav-link flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold text-blue-100"><span class="nav-icon">↗</span><span class="sidebar-label">View website</span></a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link flex w-full items-center gap-3 rounded-xl px-3 py-2 text-xs font-bold text-rose-300"><span class="nav-icon">⇥</span><span class="sidebar-label">Logout</span></button></form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex min-h-[68px] items-center gap-3 border-b border-slate-200/80 bg-white/90 px-3 py-3 shadow-sm backdrop-blur-xl sm:px-5">
            <button id="sidebar-toggle" type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-lg text-slate-700 shadow-sm hover:bg-slate-50" aria-label="Toggle sidebar" aria-expanded="false">☰</button>
            <div class="hidden min-w-0 sm:block"><h1 class="text-base font-black text-slate-900">@yield('heading', 'Dashboard')</h1><p class="text-[10px] text-slate-400">Alom's English Center · Administration</p></div>
            <div class="relative ml-1 min-w-0 flex-1 sm:ml-4 sm:max-w-xl">
                <label for="admin-global-search" class="sr-only">Global search</label>
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus-within:border-blue-400 focus-within:bg-white focus-within:ring-4 focus-within:ring-blue-100"><span class="text-slate-400">⌕</span><input id="admin-global-search" type="search" autocomplete="off" placeholder="Search students, courses, teachers..." class="w-full min-w-0 border-0 bg-transparent p-0 text-xs text-slate-700 outline-none focus:ring-0"></div>
                <div id="admin-search-results" class="absolute left-0 right-0 top-full z-50 mt-2 hidden rounded-xl border border-slate-200 bg-white p-2 shadow-2xl"></div>
            </div>
            <div class="ml-auto flex shrink-0 items-center gap-2"><div class="hidden lg:block">@yield('actions')</div>
                <a href="{{ route('notifications.index') }}" class="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-lg text-slate-600 hover:bg-slate-50" aria-label="Notifications">🔔<span data-unread-badge="notifications" class="{{ auth()->user()->unreadNotifications()->count() ? '' : 'hidden' }} absolute -right-1 -top-1 min-w-4 rounded-full bg-rose-500 px-1 text-center text-[9px] font-black text-white">{{ auth()->user()->unreadNotifications()->count() }}</span></a>
                <a href="{{ route('messages.index') }}" class="relative hidden h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-lg text-slate-600 hover:bg-slate-50 sm:grid" aria-label="Messages">✉<span data-unread-badge="messages" class="{{ auth()->user()->receivedMessages()->whereNull('read_at')->count() ? '' : 'hidden' }} absolute -right-1 -top-1 min-w-4 rounded-full bg-rose-500 px-1 text-center text-[9px] font-black text-white">{{ auth()->user()->receivedMessages()->whereNull('read_at')->count() }}</span></a>
                <button id="admin-fullscreen" type="button" class="hidden h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-lg text-slate-600 hover:bg-slate-50 md:grid" aria-label="Toggle full screen">⛶</button>
                <details class="relative">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white p-1.5 pr-3 hover:bg-slate-50"><img src="{{ auth()->user()->avatarUrl() }}" alt="" class="h-8 w-8 rounded-lg object-cover"><span class="hidden max-w-28 truncate text-xs font-bold text-slate-700 md:block">{{ auth()->user()->name }}</span><span class="text-[10px] text-slate-400">⌄</span></summary>
                    <div class="absolute right-0 top-full z-50 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-xl"><div class="border-b border-slate-100 px-3 py-2"><b class="block truncate text-slate-800">{{ auth()->user()->name }}</b><span class="text-[10px] text-slate-400">{{ auth()->user()->roleLabel() }}</span></div><a href="{{ route('student.profile') }}" class="block rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">My profile</a><label for="admin-theme" class="block px-3 pt-2 text-[10px] font-bold uppercase text-slate-400">Appearance</label><select id="admin-theme" class="m-2 w-[calc(100%-1rem)] rounded-lg border border-slate-200 px-2 py-1.5 text-xs"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></div>
                </details>
            </div>
        </header>
        <main class="min-w-0 flex-1 p-3 sm:p-5 lg:p-6">@include('partials.flash')@yield('content')</main>
    </div>
</div>
<script>
(function(){
 const sidebar=document.getElementById('admin-sidebar'),toggle=document.getElementById('sidebar-toggle'),scrim=document.getElementById('sidebar-scrim');
 function closeMobile(){sidebar.classList.add('-translate-x-full');scrim.classList.add('hidden');toggle.setAttribute('aria-expanded','false');}
 toggle.addEventListener('click',function(){
   if(window.matchMedia('(min-width: 1024px)').matches){sidebar.dataset.collapsed=sidebar.dataset.collapsed==='true'?'false':'true';}
   else{const closed=sidebar.classList.toggle('-translate-x-full');scrim.classList.toggle('hidden',closed);toggle.setAttribute('aria-expanded',String(!closed));}
 });
 scrim.addEventListener('click',closeMobile);
 window.addEventListener('resize',()=>{if(window.matchMedia('(min-width: 1024px)').matches){sidebar.classList.remove('-translate-x-full');scrim.classList.add('hidden');}});
 const theme=document.getElementById('admin-theme');if(theme){const saved=localStorage.getItem('aec-admin-theme')||'system';theme.value=saved;function apply(value){const dark=value==='dark'||(value==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.dataset.theme=dark?'dark':'light';}theme.addEventListener('change',function(){localStorage.setItem('aec-admin-theme',this.value);apply(this.value);});apply(saved);}
 const full=document.getElementById('admin-fullscreen');if(full)full.addEventListener('click',()=>{if(!document.fullscreenElement){document.documentElement.requestFullscreen?.();}else{document.exitFullscreen?.();}});
 const search=document.getElementById('admin-global-search'),results=document.getElementById('admin-search-results');let debounce;
 if(search&&results){search.addEventListener('input',function(){clearTimeout(debounce);const q=this.value.trim();if(q.length<2){results.classList.add('hidden');results.innerHTML='';return;}debounce=setTimeout(async()=>{try{const url=new URL(@json(route('admin.search')),window.location.origin);url.searchParams.set('q',q);const response=await fetch(url,{headers:{'Accept':'application/json'},credentials:'same-origin'});if(!response.ok)throw new Error('Search failed');const data=await response.json();results.innerHTML='';if(!data.results.length){results.innerHTML='<p class="px-3 py-4 text-xs text-slate-400">No matches found.</p>';}else{data.results.forEach(item=>{const a=document.createElement('a');a.href=item.url;a.className='flex items-center justify-between rounded-lg px-3 py-2 text-xs hover:bg-slate-50';const label=document.createElement('span');label.textContent=item.label;label.className='font-semibold text-slate-700';const type=document.createElement('span');type.textContent=item.type;type.className='text-[10px] text-slate-400';a.append(label,type);results.append(a);});}results.classList.remove('hidden');}catch(e){results.classList.add('hidden');}},220);});
 document.addEventListener('click',e=>{if(!results.contains(e.target)&&e.target!==search)results.classList.add('hidden');});}
 const endpoint=@json(route('communication.unread-counts'));async function refreshCounts(){try{const response=await fetch(endpoint,{headers:{'Accept':'application/json'},credentials:'same-origin'});if(!response.ok)return;const counts=await response.json();document.querySelectorAll('[data-unread-badge]').forEach(b=>{const count=Number(counts[b.dataset.unreadBadge]||0);b.textContent=count>99?'99+':String(count);b.classList.toggle('hidden',count<1);});}catch(e){}}
 window.setInterval(refreshCounts,15000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshCounts();});
})();
</script>
@stack('scripts')
</body>
</html>
