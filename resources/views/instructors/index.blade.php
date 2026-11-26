@extends('layouts.app')

@section('title', $pageTitle)
@section('meta_description', $pageSubtitle)

@section('content')
<main class="min-h-screen bg-gradient-to-br from-indigo-50 via-blue-50 to-purple-50 px-4 py-14 sm:py-16">
    <div class="mx-auto max-w-7xl">
        <header class="mx-auto mb-14 max-w-3xl text-center">
            <span class="inline-flex items-center rounded-full border border-blue-200 bg-white/75 px-4 py-1.5 text-xs font-extrabold uppercase tracking-[.18em] text-blue-700 shadow-sm">Meet the faculty</span>
            <h1 class="mt-5 text-3xl font-black tracking-tight text-blue-900 sm:text-5xl">{{ $pageTitle }}</h1>
            <div class="mx-auto mt-5 h-1.5 w-24 rounded-full bg-gradient-to-r from-blue-600 to-sky-400"></div>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">{{ $pageSubtitle }}</p>
        </header>

        @forelse ($panels as $panelName => $instructors)
            <section class="mb-16 last:mb-0" aria-labelledby="panel-{{ $loop->index }}">
                <div class="mb-9 text-center">
                    <h2 id="panel-{{ $loop->index }}" class="inline-block border-b-4 border-blue-500 px-3 pb-3 text-2xl font-extrabold tracking-tight text-blue-900 sm:text-3xl">{{ $panelName }}</h2>
                    <p class="mt-3 text-sm text-slate-500">{{ $instructors->count() }} {{ \Illuminate\Support\Str::plural('instructor', $instructors->count()) }}</p>
                </div>

                <div class="grid items-stretch gap-x-7 gap-y-16 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($instructors as $instructor)
                        @php
                            $profile = $instructor->staffProfile;
                            $educationLine = $profile?->education
                                ?: trim(collect([$profile?->highest_qualification, $profile?->institution])->filter()->implode(' · '));
                            $educationLine = $educationLine ?: ($profile?->experience_years ? $profile->experience_years.' Years Experience' : null);
                        @endphp
                        <article class="group relative flex min-h-[330px] flex-col items-center rounded-3xl border border-white/90 bg-white px-6 pb-7 pt-0 text-center shadow-[0_16px_50px_rgba(30,64,175,.10)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_22px_60px_rgba(30,64,175,.18)]">
                            <div class="h-24 w-full rounded-t-3xl bg-gradient-to-r from-blue-600 to-sky-600"></div>
                            <a href="{{ route('instructors.show', $instructor) }}" class="-mt-12 rounded-full focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-blue-500" aria-label="View {{ $instructor->name }} profile">
                                <img src="{{ $instructor->avatarUrl() }}" alt="{{ $instructor->name }}" loading="lazy" class="h-24 w-24 rounded-full border-4 border-white bg-blue-50 object-cover shadow-lg">
                            </a>
                            <h3 class="mt-4 text-xl font-extrabold text-slate-900">
                                <a href="{{ route('instructors.show', $instructor) }}" class="transition hover:text-blue-700">{{ $instructor->name }}</a>
                            </h3>
                            @if ($profile?->subject)
                                <span class="mt-3 inline-flex items-center gap-2 rounded-full bg-sky-100 px-4 py-1.5 text-sm font-bold text-sky-800">📖 {{ $profile->subject }}</span>
                            @endif
                            @if ($instructor->designation || $profile?->designation)
                                <p class="mt-3 text-sm font-semibold text-slate-700">{{ $instructor->designation ?: $profile->designation }}</p>
                            @endif
                            @if ($educationLine)
                                <p class="mt-1 text-sm text-slate-500">{{ $educationLine }}</p>
                            @endif
                            <div class="mt-auto flex items-center justify-center gap-3 pt-6">
                                @if ($profile?->youtube_url)
                                    <a href="{{ $profile->youtube_url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $instructor->name }} on YouTube" class="grid h-10 w-10 place-items-center rounded-full bg-red-600 text-white shadow-sm transition hover:scale-110 hover:bg-red-700 focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-red-400">
                                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4l6.2 3.6-6.2 3.6Z"/></svg>
                                    </a>
                                @endif
                                @if ($profile?->facebook_url)
                                    <a href="{{ $profile->facebook_url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $instructor->name }} on Facebook" class="grid h-10 w-10 place-items-center rounded-full bg-blue-600 text-white shadow-sm transition hover:scale-110 hover:bg-blue-700 focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-blue-400">
                                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5h1.7V3.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.3V13h2.8v8h3.4Z"/></svg>
                                    </a>
                                @endif
                            </div>
                            <a href="{{ route('instructors.show', $instructor) }}" class="mt-5 inline-flex items-center gap-2 text-sm font-extrabold text-blue-700 transition hover:text-blue-900">View full profile <span aria-hidden="true">→</span></a>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-3xl border border-blue-100 bg-white/80 px-6 py-16 text-center shadow-sm">
                <h2 class="text-xl font-bold text-slate-800">Instructor profiles are being updated</h2>
                <p class="mt-2 text-slate-600">Please check back soon to meet our teaching team.</p>
            </div>
        @endforelse
    </div>
</main>
@endsection
