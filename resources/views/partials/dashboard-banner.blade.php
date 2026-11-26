@php
$bannerEnabled = \App\Models\Setting::get('dashboard_banner_enabled', '1') === '1';
$bannerImage = \App\Models\Setting::get('dashboard_banner');
$primary = \App\Models\Setting::get('dashboard_primary_color', '#059669');
$secondary = \App\Models\Setting::get('dashboard_secondary_color', '#0f172a');
$role = auth()->user()->role;
$fallback = ['student' => route('student.courses'), 'instructor' => route('teacher.courses'), 'branch_manager' => route('branch.students'), 'guardian' => route('guardian.dashboard')];
$cta = \App\Models\Setting::get('dashboard_banner_button_url') ?: ($fallback[$role] ?? route('home'));
@endphp
@if ($bannerEnabled)
<section class="relative mb-6 overflow-hidden rounded-3xl p-5 text-white shadow-lg sm:p-8" style="background: linear-gradient(115deg, {{ $secondary }}, {{ $primary }})">
@if ($bannerImageUrl)
<img src="{{ $bannerImageUrl }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
<div aria-hidden="true" class="absolute inset-0 bg-slate-950/60"></div>
@endif
<div class="relative z-10 max-w-2xl">
<p class="text-xs font-bold uppercase tracking-widest text-white/70">{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</p>
<h2 class="mt-2 text-xl font-extrabold sm:text-3xl">{{ \App\Models\Setting::get('dashboard_banner_title', 'Welcome back!') }}</h2>
<p class="mt-2 text-sm leading-6 text-white/80">{{ \App\Models\Setting::get('dashboard_banner_subtitle', 'Your learning and progress at a glance.') }}</p>
@if (\App\Models\Setting::get('dashboard_banner_button_text'))
<a href="{{ $cta }}" class="mt-4 inline-flex rounded-xl bg-white px-4 py-2.5 text-sm font-bold" style="color: {{ $secondary }}">{{ \App\Models\Setting::get('dashboard_banner_button_text') }}</a>
@endif
</div></section>
@endif
