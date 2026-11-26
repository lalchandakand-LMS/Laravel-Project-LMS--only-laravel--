@extends('layouts.student')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('content')
<div class="mb-5"><a href="{{ route('student.attendance') }}" class="inline-flex rounded-xl bg-orange-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-orange-700">My attendance report</a></div>
@include('partials.dashboard-banner')

<div class="mt-6 flex flex-wrap gap-2">
    <a href="{{ route('student.online-batches') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">💻 Online live batches</a>
    <a href="{{ route('coaching-fees.student') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">💰 Coaching fee ledger</a>
    <a href="{{ route('student.attendance') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">📋 Attendance report</a>
</div>

@if($onlineBatchEnrollments->isNotEmpty())
<section class="mt-6 rounded-2xl border border-sky-200 bg-sky-50 p-5">
    <h3 class="font-bold text-sky-950">Online live-class batches</h3>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach($onlineBatchEnrollments as $onlineEnrollment)
        <article class="rounded-xl border border-sky-100 bg-white p-4">
            <p class="font-bold text-slate-900">{{ $onlineEnrollment->course?->title }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $onlineEnrollment->batch?->name }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $onlineEnrollment->batch?->schedule_days }} {{ $onlineEnrollment->batch?->schedule_time }}</p>
        </article>
        @endforeach
    </div>
</section>
@endif

@if($coachingEnrollments->isNotEmpty() || $coachingDue > 0)
<section class="mt-6 rounded-2xl border border-orange-200 bg-orange-50 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h3 class="font-bold text-orange-950">সরাসরি কোচিং — আপনার Learning Path</h3><p class="mt-1 text-sm text-orange-900">একই শিক্ষার্থী অ্যাকাউন্টে অনলাইন কোর্স ও শাখাভিত্তিক কোচিংয়ের অগ্রগতি।</p></div>
        <div class="text-right"><p class="text-xs text-orange-800">Coaching fee due</p><p class="text-xl font-black text-emerald-950">৳{{ number_format($coachingDue,2) }}</p></div>
    </div>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach($coachingEnrollments as $coachingEnrollment)
        <article class="rounded-xl border border-orange-100 bg-white p-4">
            <p class="font-bold text-slate-900">{{ $coachingEnrollment->course?->title }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $coachingEnrollment->batch?->name }} · {{ $coachingEnrollment->batch?->branch?->name }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $coachingEnrollment->batch?->schedule_days }} {{ $coachingEnrollment->batch?->schedule_time }}</p>
        </article>
        @endforeach
    </div>
</section>
@endif

<div class="rounded-2xl bg-gradient-to-r from-slate-950 to-blue-950 p-6 text-white">
    <h2 class="text-2xl font-extrabold">Hello, {{ auth()->user()->name }} 👋</h2>
    <p class="mt-1 text-slate-300">Keep going — you're {{ $stats['in_progress'] }} course(s) in progress.</p>
    @if ($continueCourse)
        <a href="{{ route('student.learn', $continueCourse->course->slug) }}"
           class="mt-5 inline-flex items-center gap-2 rounded-xl bg-orange-400 px-5 py-2.5 font-bold text-slate-950 hover:bg-orange-300">
            Continue: {{ Str::limit($continueCourse->course->title, 32) }} →
        </a>
    @endif
</div>

@if (\App\Models\Setting::get('dashboard_widget_student_stats', '1') === '1')
<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
        ['Enrolled courses', $stats['enrolled'], '📚'],
        ['Lessons completed', $stats['lessons_done'], '✅'],
        ['Exam attempts', $stats['exam_attempts'], '📝'],
        ['Certificates', $stats['certificates'], '🏅'],
    ] as [$label, $value, $icon])
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-2xl">{{ $icon }}</p>
            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $value }}</p>
            <p class="text-sm text-slate-600">{{ $label }}</p>
        </div>
    @endforeach
</div>
@endif

<section class="mt-6 rounded-2xl border border-violet-200 bg-gradient-to-r from-violet-50 to-indigo-50 p-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-violet-700">Your learning rewards</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($gamification['xp']) }} XP</p>
            <p class="text-sm text-slate-600">Level {{ $gamification['level'] }} · {{ $gamification['streak_days'] }} day learning streak · {{ $gamification['unlocked_badges'] }} badges unlocked</p>
        </div>
        <div class="min-w-48 flex-1 sm:max-w-xs">
            <div class="flex justify-between text-xs font-semibold text-violet-800"><span>Level progress</span><span>{{ $gamification['level_progress'] }}/100 XP</span></div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-violet-200"><div class="h-full rounded-full bg-violet-600" style="width: {{ $gamification['level_progress'] }}%"></div></div>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($gamification['badges'] as $badge)
            <span title="{{ $badge['description'] }}" class="rounded-full px-3 py-1.5 text-xs font-bold {{ $badge['unlocked'] ? 'bg-white text-violet-800 ring-1 ring-violet-200' : 'bg-violet-100/70 text-violet-400' }}">
                {{ $badge['unlocked'] ? '🏅' : '🔒' }} {{ $badge['name'] }}
            </span>
        @endforeach
    </div>
</section>

<div class="mt-8 grid items-start gap-6 lg:grid-cols-[1.6fr_1fr]">
@if (\App\Models\Setting::get('dashboard_widget_student_courses', '1') === '1')
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h3 class="font-bold text-slate-900">Continue learning</h3>
            <a href="{{ route('student.courses') }}" class="text-sm font-semibold text-orange-700 hover:underline">All courses →</a>
        </div>
        <div class="mt-4 space-y-3">
            @forelse ($enrollments as $enrollment)
                @php($course = $enrollment->course)
                @if ($course)
                    @php($progress = $course->progressFor(auth()->user()))
                    <a href="{{ route('student.learn', $course->slug) }}" class="flex items-center gap-4 rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                        <img src="{{ $course->thumbnail_url }}" class="h-14 w-20 rounded-lg object-cover" alt="">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-900">{{ $course->title }}</p>
                            <div class="mt-1 flex items-center gap-2">
                                <div class="h-1.5 w-full max-w-[160px] overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $progress }}%"></div>
                                </div>
                                <span class="text-xs text-slate-500">{{ $progress }}%</span>
                            </div>
                        </div>
                        <span class="text-emerald-700">→</span>
                    </a>
                @endif
            @empty
                <p class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-600">
                    You are not enrolled in any course yet.
                    <a href="{{ route('courses.index') }}" class="font-semibold text-orange-700 underline">Browse courses</a>
                </p>
            @endforelse
        </div>
    </section>
@endif

    <div class="space-y-6">
        @if (\App\Models\Setting::get('dashboard_widget_student_exams', '1') === '1')
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Upcoming exams</h3>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($upcomingExams as $exam)
                    <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <div>
                            <p class="font-semibold text-slate-800">{{ Str::limit($exam->title, 30) }}</p>
                            <p class="text-xs text-slate-500">{{ $exam->starts_at ? $exam->starts_at->format('d M, h:i A') : 'Open now' }} · {{ $exam->duration_minutes }}m</p>
                        </div>
                        <a href="{{ route('student.exams.show', $exam) }}" class="rounded bg-white px-2 py-1 text-xs font-bold text-orange-700 ring-1 ring-slate-200">Open</a>
                    </li>
                @empty
                    <li class="text-slate-500">No exams scheduled.</li>
                @endforelse
            </ul>
        </section>
@endif

        @if (\App\Models\Setting::get('dashboard_widget_student_activity', '1') === '1')
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">Recent activity</h3>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($recentLessons as $progress)
                    <li class="flex items-center gap-2 text-slate-600">
                        <span class="{{ $progress->completed_at ? 'text-emerald-500' : 'text-amber-700' }}">●</span>
                        <span class="truncate">{{ Str::limit(optional($progress->lesson)->title, 34) }}</span>
                        <span class="ml-auto text-xs text-slate-500">{{ $progress->updated_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">No activity yet.</li>
                @endforelse
            </ul>
        </section>
@endif
    </div>
</div>
@endsection
