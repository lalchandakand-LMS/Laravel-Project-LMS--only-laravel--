@extends('layouts.app')

@section('title', $pageTitle)
@section('meta_description', $pageSubtitle)

@section('content')
<main class="min-h-screen bg-gradient-to-br from-indigo-50 via-blue-50 to-purple-50 px-4 py-12 sm:py-16">
    <div class="mx-auto max-w-6xl">
        <a href="{{ route('instructors.index') }}" class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white/80 px-4 py-2 text-sm font-bold text-blue-800 shadow-sm transition hover:bg-white">← All instructors</a>

        <section class="mt-8 overflow-hidden rounded-3xl border border-white bg-white shadow-[0_18px_60px_rgba(30,64,175,.12)]">
            <div class="h-32 bg-gradient-to-r from-blue-700 via-blue-600 to-sky-500 sm:h-40"></div>
            <div class="px-6 pb-8 sm:px-10">
                <div class="-mt-16 flex flex-col items-center gap-5 text-center sm:-mt-20 sm:flex-row sm:items-end sm:text-left">
                    <img src="{{ $instructor->avatarUrl() }}" alt="{{ $instructor->name }}" class="h-32 w-32 rounded-full border-4 border-white bg-blue-50 object-cover shadow-xl sm:h-40 sm:w-40">
                    <div class="min-w-0 flex-1 pb-2">
                        <h1 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">{{ $instructor->name }}</h1>
                        @if ($instructor->designation || $instructor->staffProfile?->designation)
                            <p class="mt-2 text-base font-semibold text-blue-800">{{ $instructor->designation ?: $instructor->staffProfile->designation }}</p>
                        @endif
                        @if ($instructor->staffProfile?->subject)
                            <span class="mt-3 inline-flex rounded-full bg-sky-100 px-4 py-1.5 text-sm font-bold text-sky-800">📖 {{ $instructor->staffProfile->subject }}</span>
                        @endif
                    </div>
                    <div class="flex gap-3 pb-2">
                        @if ($instructor->staffProfile?->youtube_url)
                            <a href="{{ $instructor->staffProfile->youtube_url }}" target="_blank" rel="noopener noreferrer" aria-label="YouTube" class="grid h-11 w-11 place-items-center rounded-full bg-red-600 text-white hover:bg-red-700"><span class="font-black">▶</span></a>
                        @endif
                        @if ($instructor->staffProfile?->facebook_url)
                            <a href="{{ $instructor->staffProfile->facebook_url }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="grid h-11 w-11 place-items-center rounded-full bg-blue-600 text-white hover:bg-blue-700"><span class="text-xl font-black">f</span></a>
                        @endif
                    </div>
                </div>

                @php
                    $profile = $instructor->staffProfile;
                    $educationLine = $profile?->education ?: trim(collect([$profile?->highest_qualification, $profile?->institution])->filter()->implode(' · '));
                    $bio = $profile?->professional_bio ?: $instructor->bio;
                @endphp
                <div class="mt-9 grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-900">About {{ $instructor->name }}</h2>
                        <div class="mt-3 whitespace-pre-line text-sm leading-8 text-slate-600">{{ $bio ?: 'This instructor profile will be updated soon.' }}</div>
                    </div>
                    <aside class="rounded-2xl border border-blue-100 bg-blue-50/70 p-5">
                        <h2 class="font-extrabold text-blue-950">Teaching profile</h2>
                        <dl class="mt-4 space-y-4 text-sm">
                            @if ($profile?->panel_group)
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Panel</dt><dd class="mt-1 font-semibold text-slate-800">{{ $profile->panel_group }}</dd></div>
                            @endif
                            @if ($educationLine)
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Education / Experience</dt><dd class="mt-1 font-semibold text-slate-800">{{ $educationLine }}</dd></div>
                            @endif
                            @if ($profile?->experience_years)
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Experience</dt><dd class="mt-1 font-semibold text-slate-800">{{ $profile->experience_years }} years</dd></div>
                            @endif
                            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Published courses</dt><dd class="mt-1 font-semibold text-slate-800">{{ $courses->count() }}</dd></div>
                        </dl>
                    </aside>
                </div>
            </div>
        </section>

        <section class="mt-14">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 class="text-2xl font-black text-blue-950 sm:text-3xl">Published Courses</h2><p class="mt-2 text-sm text-slate-600">Explore the courses taught by {{ $instructor->name }}.</p></div>
            </div>
            @if ($courses->isNotEmpty())
                <div class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($courses as $course)
                        <article class="overflow-hidden rounded-2xl border border-white bg-white shadow-[0_12px_36px_rgba(30,64,175,.09)] transition hover:-translate-y-1 hover:shadow-lg">
                            <a href="{{ route('courses.show', $course) }}" class="block">
                                <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" loading="lazy" class="h-48 w-full object-cover">
                            </a>
                            <div class="p-5">
                                @if ($course->category)<p class="text-xs font-bold uppercase tracking-wide text-blue-700">{{ $course->category->name }}</p>@endif
                                <h3 class="mt-2 text-lg font-extrabold text-slate-900"><a href="{{ route('courses.show', $course) }}" class="hover:text-blue-700">{{ $course->title }}</a></h3>
                                @if ($course->subtitle)<p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">{{ $course->subtitle }}</p>@endif
                                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4 text-sm">
                                    <span class="text-slate-500">{{ $course->lessons_count }} lessons</span>
                                    <span class="font-extrabold text-blue-800">{{ $course->final_price > 0 ? '৳'.number_format($course->final_price, 0) : 'Free' }}</span>
                                </div>
                                <a href="{{ route('courses.show', $course) }}" class="mt-4 inline-flex w-full justify-center rounded-xl bg-blue-700 px-4 py-3 text-sm font-bold text-white transition hover:bg-blue-800">View course</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="mt-7 rounded-2xl border border-blue-100 bg-white/80 px-6 py-12 text-center text-slate-600">No published courses are currently listed for this instructor.</div>
            @endif
        </section>
    </div>
</main>
@endsection
