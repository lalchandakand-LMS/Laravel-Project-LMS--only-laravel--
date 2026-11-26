@extends('layouts.portal')
@section('title', 'Live classes')
@section('heading', 'My live classes')
@section('actions')
    <a href="{{ route('teacher.live-classes', ['view' => 'list']) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">List view</a>
    <a href="{{ route('teacher.live-classes', ['view' => 'calendar', 'month' => now()->format('Y-m')]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Calendar</a>
    <a href="{{ route('admin.live-classes.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">+ Schedule class</a>
@endsection
@section('content')
@if ($viewMode === 'calendar')
    <div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <a href="{{ route('teacher.live-classes', ['view' => 'calendar', 'month' => $calendarMonth->copy()->subMonth()->format('Y-m')]) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold">← Previous</a>
        <h2 class="font-bold text-slate-900">{{ $calendarMonth->format('F Y') }}</h2>
        <a href="{{ route('teacher.live-classes', ['view' => 'calendar', 'month' => $calendarMonth->copy()->addMonth()->format('Y-m')]) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold">Next →</a>
    </div>
    <div class="space-y-3">
        @foreach (\Carbon\CarbonPeriod::create($calendarMonth->copy()->startOfMonth(), $calendarMonth->copy()->endOfMonth()) as $day)
            @php($dayClasses = $calendarClasses->get($day->toDateString(), collect()))
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-800">{{ $day->format('D, d M') }}</span>
                    <span class="text-xs text-slate-500">{{ $dayClasses->count() }} class(es)</span>
                </div>
                @forelse ($dayClasses as $class)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-0">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $class->starts_at?->format('h:i A') }} · {{ $class->title }}</p>
                            <p class="text-xs text-slate-500">{{ $class->course?->title }} · {{ $class->batch?->name ?? 'Course-wide class' }}</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs font-bold {{ $class->statusBadge() }}">{{ $class->statusLabel() }}</span>
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-slate-400">No classes scheduled.</p>
                @endforelse
            </section>
        @endforeach
    </div>
@else
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-5 py-3">Class</th><th class="px-5 py-3">Course</th><th class="px-5 py-3">Starts</th><th class="px-5 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($classes as $class)
                <tr>
                    <td class="px-5 py-3">
                        <p class="font-semibold text-slate-800">{{ $class->title }}</p>
                        <p class="text-xs text-slate-500">{{ $class->platform }} · {{ $class->duration_minutes }} min</p>
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($class->course?->title, 40) }}</td>
                    <td class="px-5 py-3">
                        <p class="font-semibold text-slate-700">{{ $class->starts_at?->format('d M Y, h:i A') }}</p>
                        <p class="text-xs text-slate-500">{{ $class->starts_at?->diffForHumans() }}</p>
                    </td>
                    <td class="px-5 py-3 text-right">
                        @if ($class->meeting_url)
                            <a href="{{ $class->meeting_url }}" target="_blank" rel="noopener" class="text-sm font-bold text-emerald-700">Open room →</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No classes scheduled for your courses.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $classes->links() }}</div>
@endif
@endsection
