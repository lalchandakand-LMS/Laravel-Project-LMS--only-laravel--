@extends('layouts.student')
@section('title', $lesson->title)
@section('heading', Str::limit($course->title, 60))
@section('content')
@php
    $videoPath = parse_url((string) $lesson->video_url, PHP_URL_PATH) ?: '';
    $isYoutubeVideo = $lesson->type === 'video' && (bool) preg_match('/(?:youtube\\.com\\/(?:watch\\?v=|embed\\/)|youtu\\.be\\/)/i', (string) $lesson->video_url);
    $isVimeoVideo = $lesson->type === 'video' && (bool) preg_match('/vimeo\\.com\\/\\d+/i', (string) $lesson->video_url);
    $isDirectVideo = $lesson->type === 'video' && (bool) preg_match('/\\.(mp4|webm|ogg)$/i', $videoPath);
    $videoSrc = $lesson->embedUrl();
    if ($isYoutubeVideo && $videoSrc) {
        $videoSrc .= (str_contains($videoSrc, '?') ? '&' : '?').'enablejsapi=1&origin='.urlencode(request()->getSchemeAndHttpHost());
    } elseif ($isVimeoVideo && $videoSrc) {
        $videoSrc .= (str_contains($videoSrc, '?') ? '&' : '?').'api=1&player_id=lesson-video-player';
    }
    $watchedSeconds = (int) ($watchedSeconds ?? 0);
@endphp
<div class="grid gap-5 xl:grid-cols-[1fr_340px]">
    <div class="space-y-4">
        <div class="overflow-hidden rounded-2xl bg-black shadow-sm">
            @if ($isDirectVideo)
                <div class="aspect-video">
                    <video id="lesson-video-player" class="h-full w-full" controls playsinline preload="metadata"
                           src="{{ $lesson->video_url }}" data-resume-seconds="{{ $watchedSeconds }}"
                           data-heartbeat-url="{{ route('student.learn.heartbeat', [$course->slug, $lesson->id]) }}">
                        Your browser does not support HTML5 video.
                    </video>
                </div>
            @elseif ($lesson->type === 'video' && $videoSrc)
                <div class="aspect-video">
                    <iframe id="lesson-video-player" src="{{ $videoSrc }}" class="h-full w-full"
                            title="{{ $lesson->title }}" data-player="{{ $isYoutubeVideo ? 'youtube' : ($isVimeoVideo ? 'vimeo' : 'external') }}"
                            data-resume-seconds="{{ $watchedSeconds }}"
                            data-heartbeat-url="{{ route('student.learn.heartbeat', [$course->slug, $lesson->id]) }}"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture; fullscreen"
                            referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                </div>
            @elseif ($lesson->type === 'video')
                <div class="grid aspect-video place-items-center text-slate-400">No video uploaded for this lesson.</div>
            @else
                <div class="flex items-center gap-3 bg-slate-900 px-5 py-4 text-white">
                    <span class="text-xl">{{ $lesson->type === 'pdf' ? '📄' : '📖' }}</span>
                    <p class="font-semibold">{{ $lesson->title }}</p>
                </div>
            @endif
        </div>
        @if ($watchedSeconds > 0 && $lesson->type === 'video')
            <p class="text-xs text-slate-500" role="status">Your saved resume point: {{ gmdate('H:i:s', $watchedSeconds) }}. Playback will continue from here when supported by the video provider.</p>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ optional($lesson->section)->title ?? 'Lesson' }}</p>
                    <h1 class="mt-1 text-2xl font-extrabold text-slate-900">{{ $lesson->title }}</h1>
                </div>
                <form method="POST" action="{{ route('student.learn.complete', [$course->slug, $lesson->id]) }}">
                    @csrf
                    <button class="rounded-xl {{ in_array($lesson->id, $completedIds) ? 'bg-emerald-100 text-emerald-700' : 'bg-emerald-700 text-white hover:bg-emerald-800' }} px-5 py-2.5 text-sm font-bold">
                        {{ in_array($lesson->id, $completedIds) ? '✓ Completed' : 'Mark as complete' }}
                    </button>
                </form>
            </div>

            @if ($lesson->content)
                <div class="prose prose-sm mt-5 max-w-none whitespace-pre-line text-slate-700">{{ $lesson->content }}</div>
            @endif

            @if ($lesson->attachment)
                <a href="{{ route('student.learn.attachment', [$course->slug, $lesson->id]) }}" download class="mt-5 inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    📎 Download lesson resource
                </a>
            @endif

            @if ($exams->count())
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-bold text-amber-900">Exam available for this lesson</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($exams as $exam)
                            <li class="flex items-center justify-between">
                                <span>{{ $exam->title }}</span>
                                <a href="{{ route('student.exams.show', $exam) }}" class="rounded bg-white px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-200">Take exam</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
                @if ($prev)
                    <a href="{{ route('student.learn.lesson', [$course->slug, $prev->id]) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Previous</a>
                @else <span></span> @endif
                @if ($next)
                    <a href="{{ route('student.learn.lesson', [$course->slug, $next->id]) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Next lesson →</a>
                @endif
            </div>
        </div>
    </div>

    <aside class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between text-sm">
                <span class="font-bold text-slate-900">Your progress</span>
                <span class="font-semibold text-emerald-700">{{ $progress }}%</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $progress }}%"></div>
            </div>
            @if ($progress >= 100 && $course->has_certificate)
                <a href="{{ route('student.certificates.show', $course->slug) }}" class="mt-4 block rounded-lg bg-amber-500 px-4 py-2 text-center text-sm font-bold text-white">🏅 Get certificate</a>
            @endif
        </div>

        <div class="max-h-[70vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            @foreach ($course->sections as $section)
                <div class="border-b border-slate-100">
                    <p class="bg-slate-50 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-slate-500">{{ $section->title }}</p>
                    <ul>
                        @foreach ($section->lessons as $item)
                            @php($done = in_array($item->id, $completedIds))
                            <li>
                                <a href="{{ route('student.learn.lesson', [$course->slug, $item->id]) }}"
                                   class="flex items-center gap-2 px-4 py-2.5 text-sm {{ $item->id === $lesson->id ? 'bg-emerald-50 font-semibold text-emerald-700' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <span class="{{ $done ? 'text-emerald-700' : 'text-slate-300' }}">{{ $done ? '✓' : '○' }}</span>
                                    <span class="flex-1 truncate">{{ $item->title }}</span>
                                    <span class="text-xs text-slate-500">{{ $item->duration_minutes }}m</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        @if ($announcements->count())
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">Announcements</h3>
                <ul class="mt-2 space-y-2 text-sm text-slate-600">
                    @foreach ($announcements as $a)
                        <li class="rounded-lg bg-slate-50 p-2">
                            <p class="font-semibold">{{ $a->title }}</p>
                            <p class="text-xs text-slate-500">{{ Str::limit($a->body, 90) }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </aside>
</div>

@if ($lesson->type === 'video' && ($isDirectVideo || $isYoutubeVideo || $isVimeoVideo))
<script>
(() => {
    const player = document.getElementById('lesson-video-player');
    if (!player) return;

    const resumeAt = Math.max(0, Number(player.dataset.resumeSeconds || 0));
    const endpoint = player.dataset.heartbeatUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let lastSent = -1;
    let latestTime = 0;
    let trackingTimer = null;

    const savePosition = async (seconds) => {
        const position = Math.max(0, Math.floor(Number(seconds) || 0));
        if (!endpoint || !csrf || position <= lastSent) return;
        lastSent = position;
        try {
            await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({seconds: position})
            });
        } catch (_) {
            lastSent = Math.min(lastSent, position - 1);
        }
    };

    const startTracking = (getTime) => {
        if (trackingTimer) clearInterval(trackingTimer);
        trackingTimer = setInterval(async () => {
            try {
                latestTime = await getTime();
                await savePosition(latestTime);
            } catch (_) {}
        }, 15000);
    };
    const stopTracking = async () => {
        if (trackingTimer) clearInterval(trackingTimer);
        trackingTimer = null;
        await savePosition(latestTime);
    };

    if (player.tagName === 'VIDEO') {
        player.addEventListener('loadedmetadata', () => {
            if (resumeAt > 0 && Number.isFinite(player.duration) && resumeAt < player.duration) {
                player.currentTime = resumeAt;
            }
        }, {once: true});
        player.addEventListener('timeupdate', () => { latestTime = player.currentTime; });
        player.addEventListener('play', () => startTracking(() => player.currentTime));
        player.addEventListener('pause', stopTracking);
        player.addEventListener('ended', stopTracking);
        window.addEventListener('pagehide', () => savePosition(latestTime));
        return;
    }

    const provider = player.dataset.player;
    if (provider === 'youtube') {
        const boot = () => {
            if (!window.YT?.Player) return;
            const yt = new YT.Player(player, {
                events: {
                    onReady: (event) => {
                        if (resumeAt > 0) event.target.seekTo(resumeAt, true);
                    },
                    onStateChange: (event) => {
                        if (event.data === YT.PlayerState.PLAYING) {
                            startTracking(() => yt.getCurrentTime());
                        } else {
                            try { latestTime = yt.getCurrentTime(); } catch (_) {}
                            stopTracking();
                        }
                    }
                }
            });
        };
        if (window.YT?.Player) boot();
        else {
            window.onYouTubeIframeAPIReady = boot;
            const script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(script);
        }
    } else if (provider === 'vimeo') {
        const boot = () => {
            if (!window.Vimeo?.Player) return;
            const vimeo = new Vimeo.Player(player);
            vimeo.ready().then(() => resumeAt > 0 ? vimeo.setCurrentTime(resumeAt) : null).catch(() => {});
            vimeo.on('play', () => startTracking(() => vimeo.getCurrentTime()));
            vimeo.on('timeupdate', (event) => { latestTime = event.seconds; });
            vimeo.on('pause', stopTracking);
            vimeo.on('ended', stopTracking);
        };
        if (window.Vimeo?.Player) boot();
        else {
            const script = document.createElement('script');
            script.src = 'https://player.vimeo.com/api/player.js';
            script.onload = boot;
            document.head.appendChild(script);
        }
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) savePosition(latestTime);
    });
    window.addEventListener('pagehide', () => savePosition(latestTime));
})();
</script>
@endif
@endsection
