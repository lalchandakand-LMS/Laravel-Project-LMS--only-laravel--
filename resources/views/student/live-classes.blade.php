@extends('layouts.student')
@section('title', 'Live classes')
@section('heading', 'Live classes')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('student.live-classes', ['view' => 'list']) }}" class="rounded-lg px-3 py-2 text-sm font-bold {{ $viewMode === 'list' ? 'bg-emerald-700 text-white' : 'border border-slate-200 text-slate-600' }}">List view</a>
        <a href="{{ route('student.live-classes', ['view' => 'calendar', 'month' => ($calendarMonth ?? now())->format('Y-m')]) }}" class="rounded-lg px-3 py-2 text-sm font-bold {{ $viewMode === 'calendar' ? 'bg-emerald-700 text-white' : 'border border-slate-200 text-slate-600' }}">Calendar</a>
    </div>
    @if ($viewMode === 'calendar')
        <div class="flex items-center gap-2">
            <a href="{{ route('student.live-classes', ['view' => 'calendar', 'month' => $calendarMonth->copy()->subMonth()->format('Y-m')]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold">←</a>
            <span class="min-w-32 text-center font-bold text-slate-800">{{ $calendarMonth->format('F Y') }}</span>
            <a href="{{ route('student.live-classes', ['view' => 'calendar', 'month' => $calendarMonth->copy()->addMonth()->format('Y-m')]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold">→</a>
        </div>
    @endif
</div>

@if ($viewMode === 'calendar')
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
                    <p class="px-4 py-3 text-sm text-slate-400">No scheduled classes.</p>
                @endforelse
            </section>
        @endforeach
    </div>
@else
    <div>
        <h2 class="text-xl font-extrabold text-slate-900">Your class schedule</h2>
        <p class="mt-1 text-sm text-slate-500">Live sessions and replays for courses you are currently enrolled in.</p>
    </div>
    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $classes->total() }} sessions</span>
</div>

<div class="grid gap-4 xl:grid-cols-2">
    @forelse ($classes as $class)
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $class->course?->title }}</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-900">{{ $class->title }}</h3>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $class->statusBadge() }}">{{ $class->statusLabel() }}</span>
            </div>

            @if ($class->description)
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $class->description }}</p>
            @endif

            <div class="mt-4 grid gap-2 rounded-xl bg-slate-50 p-3 text-sm sm:grid-cols-2">
                <div>
                    <p class="text-xs text-slate-500">Date & time</p>
                    <p class="font-semibold text-slate-800">{{ $class->starts_at?->format('d M Y, h:i A') }}</p>
                    <p class="text-xs text-slate-500">{{ $class->starts_at?->diffForHumans() }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Platform & duration</p>
                    <p class="font-semibold text-slate-800">{{ $class->platform }} · {{ $class->duration_minutes }} minutes</p>
                </div>
            </div>

            @if ($class->isCancelled())
                <div class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    This class has been cancelled.
                    @if ($class->cancellation_reason) <span>{{ $class->cancellation_reason }}</span> @endif
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($class->isCancelled())
                    <p class="text-xs font-medium text-rose-700">The meeting link is no longer available.</p>
                @elseif ($class->canJoin() && $class->meeting_url)
                    <a href="{{ $class->meeting_url }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">
                        Join live class ↗
                    </a>
                    @if ($class->meeting_password)
                        <span class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">Password: <strong>{{ $class->meeting_password }}</strong></span>
                    @endif
                @elseif ($class->canJoin())
                    <p class="text-xs font-medium text-amber-700">Meeting details are not available yet. Please contact your teacher.</p>
                @elseif (! $class->isUpcoming() && $class->recording_url)
                    <a href="{{ $class->recording_url }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 rounded-lg bg-indigo-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-800">
                        Watch recording ↗
                    </a>
                @elseif ($class->isUpcoming())
                    <p class="text-xs font-medium text-slate-500">The join link unlocks 10 minutes before the class starts.</p>
                @else
                    <p class="text-xs text-slate-500">This session has ended. No recording has been published.</p>
                @endif
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center xl:col-span-2">
            <div class="text-4xl">🎥</div>
            <h3 class="mt-3 font-bold text-slate-900">No live classes scheduled</h3>
            <p class="mt-1 text-sm text-slate-500">When your teacher schedules a session for an enrolled course, it will appear here.</p>
        </div>
    @endforelse
</div>

@if ($classes->hasPages())
    <div class="mt-5">{{ $classes->links() }}</div>
@endif
@endif
@endsection
