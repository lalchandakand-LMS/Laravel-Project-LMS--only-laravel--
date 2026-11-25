@extends('layouts.admin')
@section('title', 'Dashboard')
@section('heading', 'Overview')
@section('actions')
<a href="{{ route('admin.settings.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Customize dashboard</a>
@endsection
@section('content')
<div class="mb-5 flex flex-wrap gap-3"><a href="{{ route('admin.attendance.index') }}" class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white">Review teacher attendance</a></div>
@php
    $brandPrimary = \App\Models\Setting::get('dashboard_primary_color', '#059669');
    $brandSecondary = \App\Models\Setting::get('dashboard_secondary_color', '#0f172a');
    $widgetOn = fn ($name) => \App\Models\Setting::get('dashboard_widget_'.$name, '1') === '1';
    $bannerEnabled = \App\Models\Setting::get('dashboard_banner_enabled', '1') === '1';
    $bannerImage = \App\Models\Setting::get('dashboard_banner');
    $bannerUrl = \App\Models\Setting::get('dashboard_banner_button_url');
@endphp
@if ($bannerEnabled)
<section class="relative mb-6 overflow-hidden rounded-3xl p-7 text-white shadow-lg sm:p-9" style="background: {{ $bannerImage ? 'linear-gradient(90deg, rgba(15,23,42,.92), rgba(15,23,42,.35)), url('.asset('storage/'.$bannerImage).') center/cover' : 'linear-gradient(115deg, '.$brandSecondary.', '.$brandPrimary.')' }}">
    <div class="relative z-10 max-w-2xl">
        <p class="text-xs font-bold uppercase tracking-[.22em] text-white/70">{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</p>
        <h2 class="mt-3 text-2xl font-extrabold sm:text-3xl">{{ \App\Models\Setting::get('dashboard_banner_title', 'Welcome back!') }}</h2>
        <p class="mt-2 max-w-xl text-sm leading-6 text-white/80">{{ \App\Models\Setting::get('dashboard_banner_subtitle', 'Here is what is happening at your academy today.') }}</p>
        <a href="{{ $bannerUrl ?: route('admin.courses.index') }}" class="mt-5 inline-flex rounded-xl bg-white px-5 py-2.5 text-sm font-bold" style="color: {{ $brandSecondary }}">{{ \App\Models\Setting::get('dashboard_banner_button_text', 'Manage courses') }}</a>
    </div>
    <div class="pointer-events-none absolute -right-12 -top-20 h-72 w-72 rounded-full border-[35px] border-white/10"></div>
</section>
@endif

@if ($widgetOn('stats'))
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([['Revenue', '৳'.number_format($stats['revenue'], 0), '💰', 'bg-emerald-50 text-emerald-700'], ['Students', number_format($stats['students']), '👥', 'bg-sky-50 text-sky-700'], ['Enrollments', number_format($stats['enrollments']), '🎓', 'bg-violet-50 text-violet-700'], ['Pending orders', number_format($stats['pending_orders']), '⏳', 'bg-amber-50 text-amber-700']] as [$label, $value, $icon, $class])
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><span class="grid h-10 w-10 place-items-center rounded-xl {{ $class }} text-lg">{{ $icon }}</span><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</span></div><p class="mt-3 text-2xl font-extrabold text-slate-900">{{ $value }}</p></div>
    @endforeach
</div>
@endif

<div class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
    @if ($widgetOn('chart'))
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 class="font-bold text-slate-900">Revenue (last 6 months)</h3><div class="mt-6 flex h-48 items-end gap-4">
        @php($max = max(array_merge($chart['values'], [1])))
        @foreach ($chart['labels'] as $i => $label)<div class="flex flex-1 flex-col items-center gap-2"><span class="text-[10px] font-semibold text-slate-500">৳{{ number_format($chart['values'][$i], 0) }}</span><div class="w-full rounded-t-lg" style="height: {{ max(4, ($chart['values'][$i] / $max) * 140) }}px;background:{{ $brandPrimary }}"></div><span class="text-xs text-slate-500">{{ $label }}</span></div>@endforeach
    </div></section>
    @endif
    @if ($widgetOn('courses'))
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 class="font-bold text-slate-900">Top courses</h3><ul class="mt-4 space-y-3 text-sm">@foreach ($topCourses as $course)<li class="flex items-center justify-between gap-2"><a href="{{ route('admin.courses.edit', $course) }}" class="truncate text-slate-700 hover:text-emerald-700">{{ $course->title }}</a><span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold">{{ $course->enrollments_count }}</span></li>@endforeach</ul></section>
    @endif
</div>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    @if ($widgetOn('orders'))
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm"><h3 class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Recent orders</h3><table class="w-full text-sm"><tbody class="divide-y divide-slate-100">@foreach ($recentOrders as $order)<tr><td class="px-5 py-3"><a href="{{ route('admin.orders.show', $order->order_no) }}" class="font-semibold text-emerald-700">#{{ $order->order_no }}</a><p class="text-xs text-slate-500">{{ optional($order->user)->name }}</p></td><td class="px-5 py-3 text-right font-bold">৳{{ number_format($order->total) }}</td><td class="px-5 py-3 text-right"><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $order->statusBadge() }}">{{ $order->status }}</span></td></tr>@endforeach</tbody></table></section>
    @endif
    @if ($widgetOn('enrollments'))
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm"><h3 class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">New enrollments</h3><table class="w-full text-sm"><tbody class="divide-y divide-slate-100">@foreach ($recentEnrollments as $enrollment)<tr><td class="px-5 py-3">{{ optional($enrollment->user)->name }}<p class="text-xs text-slate-500">{{ Str::limit(optional($enrollment->course)->title, 34) }}</p></td><td class="px-5 py-3 text-right text-xs text-slate-500">{{ $enrollment->created_at->diffForHumans() }}</td></tr>@endforeach</tbody></table></section>
    @endif
</div>
@if ($widgetOn('reviews') && $pendingReviews->count())
<section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5"><div class="flex items-center justify-between"><h3 class="font-bold text-amber-900">Reviews awaiting approval ({{ $stats['pending_reviews'] }})</h3><a href="{{ route('admin.reviews.index') }}" class="text-sm font-semibold text-amber-800 underline">Review all</a></div><ul class="mt-3 space-y-2 text-sm text-amber-900">@foreach ($pendingReviews as $review)<li class="rounded-lg bg-white/70 px-3 py-2">{{ $review->user->name }} · {{ $review->rating }}★ — {{ Str::limit($review->comment, 80) }}</li>@endforeach</ul></section>
@endif
@endsection
