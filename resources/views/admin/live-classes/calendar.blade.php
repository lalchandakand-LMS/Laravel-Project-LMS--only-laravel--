@extends('layouts.admin')
@section('title', 'Live class calendar')
@section('heading', 'Live class calendar')
@section('actions')
    <a href="{{ route('admin.live-classes.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">List view</a>
    <a href="{{ route('admin.live-classes.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">+ Schedule class</a>
@endsection
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4">
    <a href="{{ route('admin.live-classes.calendar', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold">← Previous</a>
    <h2 class="text-lg font-bold text-slate-900">{{ $month->format('F Y') }}</h2>
    <a href="{{ route('admin.live-classes.calendar', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold">Next →</a>
</div>
<div class="space-y-3">
    @foreach (\Carbon\CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()) as $day)
        @php($dayClasses = $classes->get($day->toDateString(), collect()))
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3">
                <div>
                    <p class="font-bold text-slate-900">{{ $day->format('l, d F') }}</p>
                    <p class="text-xs text-slate-500">{{ $dayClasses->count() }} scheduled class(es)</p>
                </div>
                @if ($day->isToday()) <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Today</span> @endif
            </div>
            @forelse ($dayClasses as $class)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-0">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $class->starts_at?->format('h:i A') }} — {{ $class->title }}</p>
                        <p class="text-xs text-slate-500">{{ $class->course?->title }} · {{ $class->batch?->name ?? 'All eligible course students' }} · {{ $class->platform }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-2 py-1 text-xs font-bold {{ $class->statusBadge() }}">{{ $class->statusLabel() }}</span>
                        <a href="{{ route('admin.live-classes.edit', $class) }}" class="text-sm font-semibold text-emerald-700">Edit</a>
                    </div>
                </div>
            @empty
                <p class="px-4 py-3 text-sm text-slate-400">No classes scheduled.</p>
            @endforelse
        </section>
    @endforeach
</div>
@endsection
