@extends('layouts.admin')
@section('title', 'Admin Dashboard')
@section('heading', 'Dashboard')
@section('content')
@php
    $brandPrimary = \App\Models\Setting::get('dashboard_primary_color', '#2563eb');
    $bannerImage = \App\Models\Setting::get('dashboard_banner');
    $bannerEnabled = \App\Models\Setting::get('dashboard_banner_enabled', '1') === '1';
    $bannerButtonText = \App\Models\Setting::get('dashboard_banner_button_text', 'Manage courses');
    $bannerButtonUrl = \App\Models\Setting::get('dashboard_banner_button_url') ?: route('admin.courses.index');
    $series = [
        ['label' => 'Students', 'values' => $analytics['students'], 'color' => '#2563eb'],
        ['label' => 'Enrollments', 'values' => $analytics['enrollments'], 'color' => '#10b981'],
        ['label' => 'Course Views', 'values' => $analytics['course_views'], 'color' => '#8b5cf6'],
    ];
    $allValues = collect($series)->flatMap(fn ($item) => $item['values'])->all();
    $chartMax = max(1, (int) max($allValues ?: [1]));
    $chartPoints = fn ($values) => collect($values)->values()->map(fn ($value, $i) =>
        number_format(42 + ($i * (536 / max(1, count($values) - 1))), 1, '.', '').','.number_format(158 - (($value / $chartMax) * 125), 1, '.', '')
    )->implode(' ');
    $kpis = [
        ['key' => 'students', 'label' => 'Total Students', 'value' => $stats['students'], 'icon' => '♟', 'gradient' => 'from-blue-600 to-cyan-500'],
        ['key' => 'teachers', 'label' => 'Total Teachers', 'value' => $stats['teachers'], 'icon' => '✦', 'gradient' => 'from-emerald-600 to-teal-400'],
        ['key' => 'courses', 'label' => 'Total Courses', 'value' => $stats['courses'], 'icon' => '▣', 'gradient' => 'from-violet-600 to-fuchsia-500'],
        ['key' => 'batches', 'label' => 'Total Batches', 'value' => $stats['batches'], 'icon' => '▦', 'gradient' => 'from-orange-500 to-amber-400'],
    ];
@endphp

@if ($bannerEnabled)
<section id="admin-dashboard-banner" class="relative isolate mb-6 flex min-h-[250px] overflow-hidden rounded-[28px] bg-gradient-to-r from-blue-950 via-blue-800 to-cyan-700 text-white shadow-xl sm:min-h-[280px]" aria-labelledby="admin-dashboard-banner-title">
    @if ($bannerImage)
        <img src="{{ media_url($bannerImage) }}" alt="" aria-hidden="true" class="absolute inset-0 -z-20 h-full w-full object-cover">
    @endif
    <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/90 via-blue-950/75 to-blue-900/25"></div>
    <div class="relative z-10 flex w-full flex-col justify-center px-5 py-8 sm:px-8 lg:px-10">
        <p class="text-xs font-bold uppercase tracking-[.24em] text-cyan-100">{{ \App\Models\Setting::get('site_name', "Alom's English Center") }} · LMS v3.0</p>
        <h2 id="admin-dashboard-banner-title" class="mt-3 max-w-4xl text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl">{{ \App\Models\Setting::get('dashboard_banner_title', 'Welcome back, '.auth()->user()->name.'!') }}</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-50 sm:text-base">{{ \App\Models\Setting::get('dashboard_banner_subtitle', 'Your learning community is growing. Here is your academy at a glance.') }}</p>
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <a href="{{ $bannerButtonUrl }}" class="rounded-xl bg-white px-5 py-2.5 text-sm font-extrabold text-blue-800 shadow-lg transition hover:bg-cyan-50">{{ $bannerButtonText ?: 'Manage courses' }} <span aria-hidden="true">→</span></a>
            <a href="{{ route('admin.users.create') }}" class="rounded-xl border border-white/50 bg-white/10 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-white/20">Add a Student</a>
        </div>
        <p class="mt-4 text-xs font-semibold text-cyan-100">Total Revenue: <span class="font-black text-white">৳{{ number_format($stats['revenue'], 0) }}</span> · Pending orders: {{ number_format($stats['pending_orders']) }}</p>
    </div>
</section>
@endif

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($kpis as $kpi)
        @php($change = $growth[$kpi['key']]['percent'])
        <article class="relative isolate overflow-hidden rounded-2xl bg-gradient-to-br {{ $kpi['gradient'] }} p-5 text-white shadow-lg">
            <div class="absolute -bottom-10 -right-5 -z-10 h-36 w-56 rounded-[50%] border-[18px] border-white/10"></div>
            <div class="absolute -bottom-16 right-16 -z-10 h-36 w-56 rounded-[50%] border-[15px] border-white/10"></div>
            <div class="flex items-center justify-between"><span class="text-sm font-bold text-white/90">{{ $kpi['label'] }}</span><span class="grid h-10 w-10 place-items-center rounded-xl bg-white/20 text-xl">{{ $kpi['icon'] }}</span></div>
            <div class="mt-4 flex items-end justify-between gap-2"><p class="text-3xl font-black tracking-tight">{{ number_format($kpi['value']) }}</p><span class="rounded-full bg-white/20 px-2.5 py-1 text-xs font-extrabold">{{ $change > 0 ? '+' : '' }}{{ $change }}%</span></div>
            <p class="mt-1 text-xs font-medium text-white/75">New records vs previous month</p>
        </article>
    @endforeach
</div>

<div class="grid gap-5 2xl:grid-cols-[1.45fr_.85fr_1fr]">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h3 class="text-base font-extrabold text-slate-900">Analytics Overview</h3><p class="mt-1 text-xs text-slate-500">Monthly growth across your academy</p></div>
            <form method="GET" action="{{ route('admin.dashboard') }}"><select name="period" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600"><option value="6" @selected($analytics['period'] === 6)>Last 6 Months</option><option value="12" @selected($analytics['period'] === 12)>Last 12 Months</option></select></form>
        </div>
        <div class="mt-4 flex flex-wrap gap-4 text-xs font-semibold text-slate-600">@foreach($series as $item)<span class="inline-flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-full" style="background:{{ $item['color'] }}"></i>{{ $item['label'] }}</span>@endforeach</div>
        <div class="mt-3 overflow-hidden">
            <svg viewBox="0 0 620 185" role="img" aria-label="Monthly students, enrollments and course views line chart" class="h-52 w-full">
                @foreach([35,75,115,155] as $y)<line x1="40" y1="{{ $y }}" x2="578" y2="{{ $y }}" stroke="#e2e8f0" stroke-dasharray="4 5"/><text x="28" y="{{ $y+4 }}" text-anchor="end" fill="#94a3b8" font-size="9">{{ (int) round((158-$y)/125*$chartMax) }}</text>@endforeach
                @foreach($series as $item)<polyline points="{{ $chartPoints($item['values']) }}" fill="none" stroke="{{ $item['color'] }}" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>@foreach($item['values'] as $i => $value)<circle cx="{{ 42 + ($i * (536 / max(1, count($item['values']) - 1))) }}" cy="{{ 158 - (($value / $chartMax) * 125) }}" r="3.5" fill="{{ $item['color'] }}" stroke="white" stroke-width="1.5"/>@endforeach @endforeach
                @foreach($analytics['labels'] as $i => $label)<text x="{{ 42 + ($i * (536 / max(1, count($analytics['labels']) - 1))) }}" y="179" text-anchor="middle" fill="#64748b" font-size="10">{{ $label }}</text>@endforeach
            </svg>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-base font-extrabold text-slate-900">Course Progress</h3><p class="mt-1 text-xs text-slate-500">Active enrolments · latest 300</p>
        <div class="flex items-center justify-center py-5"><div class="grid h-36 w-36 place-items-center rounded-full" style="background:conic-gradient(#2563eb {{ $progress['overall'] }}%,#e2e8f0 0)"><div class="grid h-28 w-28 place-items-center rounded-full bg-white text-center"><div><p class="text-3xl font-black text-slate-900">{{ $progress['overall'] }}%</p><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Overall</p></div></div></div></div>
        <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between"><span class="flex items-center gap-2 text-slate-600"><i class="h-2.5 w-2.5 rounded-full bg-emerald-500"></i>Completed</span><b class="text-slate-900">{{ $progress['completed'] }}</b></div>
            <div class="flex items-center justify-between"><span class="flex items-center gap-2 text-slate-600"><i class="h-2.5 w-2.5 rounded-full bg-blue-500"></i>In Progress</span><b class="text-slate-900">{{ $progress['in_progress'] }}</b></div>
            <div class="flex items-center justify-between"><span class="flex items-center gap-2 text-slate-600"><i class="h-2.5 w-2.5 rounded-full bg-slate-300"></i>Not Started</span><b class="text-slate-900">{{ $progress['not_started'] }}</b></div>
            <p class="border-t border-slate-100 pt-3 text-[11px] text-slate-400">Based on {{ number_format($progress['sample_size']) }} active enrolments.</p>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl bg-slate-950 p-5 text-white shadow-lg">
        <div class="flex items-center justify-between"><h3 class="font-extrabold">Live Class Now</h3>@if($liveClass)<span class="rounded-full bg-rose-500/20 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-rose-300"><i class="mr-1 inline-block h-2 w-2 animate-pulse rounded-full bg-rose-500"></i>Live</span>@else<span class="rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-300">No active class</span>@endif</div>
        @if($liveClass)
            <div class="mt-4 h-32 overflow-hidden rounded-xl bg-slate-800"><img src="{{ $liveClass->course?->thumbnail_url ?? asset('images/aec-logo.svg') }}" alt="Live class thumbnail" class="h-full w-full object-cover"></div>
            <h4 class="mt-4 line-clamp-2 text-lg font-extrabold">{{ $liveClass->title }}</h4>
            <p class="mt-1 text-xs text-slate-400">{{ $liveClass->course?->title ?? 'Academy live session' }}</p>
            <div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-300"><span>◷ {{ $liveClass->starts_at?->format('h:i A') }}</span><span>◉ {{ $liveAttendanceCount }} attendance records</span></div>
            @if($liveClass->meeting_url)<a href="{{ $liveClass->meeting_url }}" target="_blank" rel="noopener noreferrer" class="mt-5 block rounded-xl bg-rose-500 px-4 py-3 text-center text-sm font-extrabold text-white hover:bg-rose-400">Join Class ↗</a>@else<a href="{{ route('admin.live-classes.index') }}" class="mt-5 block rounded-xl bg-white/10 px-4 py-3 text-center text-sm font-bold text-white">Open class details</a>@endif
        @else
            <div class="mt-4 grid h-48 place-items-center rounded-xl border border-dashed border-white/15 bg-white/5 px-5 text-center"><div><span class="text-3xl">🎥</span><p class="mt-3 font-bold">No class is live right now</p><p class="mt-1 text-xs text-slate-400">Your next scheduled session appears in Upcoming Classes.</p><a href="{{ route('admin.live-classes.create') }}" class="mt-4 inline-flex rounded-lg bg-white px-4 py-2 text-xs font-extrabold text-slate-900">Schedule a class</a></div></div>
        @endif
    </section>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-4">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-extrabold text-slate-900">Recent Activities</h3>
        <div class="mt-4 space-y-4">
            @forelse($recentActivities as $activity)
                <div class="flex gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700">↗</span><div class="min-w-0"><p class="truncate text-xs font-bold text-slate-800">{{ Str::headline(str_replace(['_', '.'], ' ', $activity->event)) }}</p><p class="mt-0.5 text-[11px] text-slate-500">{{ $activity->actor_name ?: 'System' }} · {{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}</p></div></div>
            @empty
                <p class="py-6 text-center text-xs text-slate-400">No activity has been logged yet.</p>
            @endforelse
        </div>
        <a href="{{ route('admin.activity-logs.index') }}" class="mt-4 inline-block text-xs font-bold text-blue-700">View activity log →</a>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between"><h3 class="font-extrabold text-slate-900">Upcoming Classes</h3><a href="{{ route('admin.live-classes.index') }}" class="text-[11px] font-bold text-blue-700">View all</a></div>
        <div class="mt-4 space-y-4">
            @forelse($upcomingClasses as $class)
                <div class="flex gap-3"><div class="min-w-[48px] rounded-xl bg-blue-50 px-2 py-2 text-center"><b class="block text-lg leading-5 text-blue-700">{{ $class->starts_at?->format('d') }}</b><span class="text-[9px] font-extrabold uppercase text-blue-500">{{ $class->starts_at?->format('M') }}</span></div><div class="min-w-0"><p class="line-clamp-1 text-xs font-bold text-slate-800">{{ $class->title }}</p><p class="mt-1 truncate text-[11px] text-slate-500">{{ $class->course?->title ?? $class->batch?->name ?? 'Academy class' }}</p><p class="mt-1 text-[10px] text-slate-500">{{ $class->course?->instructor?->name ?? 'Teacher TBA' }}</p><span class="mt-1 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $class->starts_at?->format('h:i A') }}</span></div></div>
            @empty
                <p class="py-6 text-center text-xs text-slate-400">No upcoming classes scheduled.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-extrabold text-slate-900">Top Students</h3>
        <div class="mt-4 space-y-4">
            @forelse($topStudents as $index => $student)
                <div><div class="flex items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-full {{ $index === 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600' }} text-[10px] font-black">{{ $index + 1 }}</span><img src="{{ $student->user?->avatarUrl() ?? asset('images/aec-logo.svg') }}" alt="" class="h-8 w-8 rounded-full object-cover"><span class="min-w-0 flex-1 truncate text-xs font-bold text-slate-800">{{ $student->user?->name ?? 'Former student' }}</span><b class="text-xs text-slate-700">{{ $student->score_percent }}%</b></div><div class="ml-8 mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-cyan-400" style="width:{{ min(100, max(0, $student->score_percent)) }}%"></div></div></div>
            @empty
                <p class="py-6 text-center text-xs text-slate-400">Student rankings appear after exam attempts are submitted.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-extrabold text-slate-900">Quick Actions</h3>
        <div class="mt-4 grid grid-cols-2 gap-2">
            <a href="{{ route('admin.users.create') }}" class="rounded-xl bg-blue-50 p-3 text-center text-[11px] font-extrabold text-blue-700 hover:bg-blue-100"><span class="mb-1 block text-xl">＋</span>Add Student</a>
            <a href="{{ route('admin.users.create', ['role' => 'instructor']) }}" class="rounded-xl bg-emerald-50 p-3 text-center text-[11px] font-extrabold text-emerald-700 hover:bg-emerald-100"><span class="mb-1 block text-xl">♟</span>Add Teacher</a>
            <a href="{{ route('admin.batches.create') }}" class="rounded-xl bg-violet-50 p-3 text-center text-[11px] font-extrabold text-violet-700 hover:bg-violet-100"><span class="mb-1 block text-xl">▦</span>Create Batch</a>
            <a href="{{ route('admin.courses.create') }}" class="rounded-xl bg-orange-50 p-3 text-center text-[11px] font-extrabold text-orange-700 hover:bg-orange-100"><span class="mb-1 block text-xl">▣</span>Add Course</a>
            <a href="{{ route('admin.exams.create') }}" class="rounded-xl bg-cyan-50 p-3 text-center text-[11px] font-extrabold text-cyan-700 hover:bg-cyan-100"><span class="mb-1 block text-xl">✎</span>Create Exam</a>
            <a href="{{ route('admin.certificates.index') }}" class="rounded-xl bg-amber-50 p-3 text-center text-[11px] font-extrabold text-amber-700 hover:bg-amber-100"><span class="mb-1 block text-xl">✧</span>Generate Certificate</a>
        </div>
    </section>
</div>
@endsection
