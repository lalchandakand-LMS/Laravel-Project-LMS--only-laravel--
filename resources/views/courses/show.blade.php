@extends('layouts.app')
@section('title', $course->meta_title ?: $course->title)
@section('meta_description', $course->meta_description ?: $course->subtitle)
@section('meta_image', $course->thumbnail ? media_url($course->thumbnail) : '')
@section('meta_image_alt', $course->title)
@section('og_type', 'product')
@push('structured_data')
<script type="application/ld+json"><?php echo json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Course',
    'name' => $course->title,
    'description' => $course->meta_description ?: $course->subtitle,
    'url' => route('courses.show', $course),
    'image' => $course->thumbnail ? media_url($course->thumbnail) : null,
    'provider' => ['@type' => 'Organization', 'name' => setting('site_name', "Alom's English Center"), 'url' => config('app.url')],
    'inLanguage' => $course->language ?: 'en',
    'educationalLevel' => $course->level,
    'offers' => ['@type' => 'Offer', 'priceCurrency' => 'BDT', 'price' => number_format((float) $course->final_price, 2, '.', ''), 'availability' => 'https://schema.org/InStock', 'url' => route('courses.show', $course)],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
@endpush

@section('content')

@php
    $trailerParts = parse_url(trim((string) $course->promo_video));
    $trailerHost = strtolower((string) ($trailerParts['host'] ?? ''));
    $trailerPath = (string) ($trailerParts['path'] ?? '');
    $trailerId = null;
    if (in_array($trailerHost, ['youtu.be', 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
        if ($trailerHost === 'youtu.be') $trailerId = trim($trailerPath, '/');
        elseif (preg_match('~^/(?:embed|shorts|live)/([\w-]{6,})~', $trailerPath, $match)) $trailerId = $match[1];
        elseif ($trailerPath === '/watch') { parse_str((string) ($trailerParts['query'] ?? ''), $trailerQuery); $trailerId = $trailerQuery['v'] ?? null; }
    }
    $trailerEmbed = is_string($trailerId) && preg_match('/^[\w-]{6,}$/', $trailerId) ? 'https://www.youtube-nocookie.com/embed/'.$trailerId : null;
    if (! $trailerEmbed && in_array($trailerHost, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true) && preg_match('~/(?:video/)?(\d+)$~', rtrim($trailerPath, '/'), $match)) $trailerEmbed = 'https://player.vimeo.com/video/'.$match[1];
@endphp

<section class="aec-course-hero text-white">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:py-16">
        <div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                @if ($course->category)
                    <a href="{{ route('categories.show', $course->category->slug) }}" class="rounded-full bg-emerald-500/20 px-3 py-1 text-emerald-300">{{ $course->category->name }}</a>
                @endif
                <span class="rounded-full bg-white/10 px-3 py-1 capitalize">{{ $course->level }}</span>
                <span class="rounded-full bg-white/10 px-3 py-1">{{ $course->language }}</span>
            </div>
            <h1 class="mt-4 text-3xl font-extrabold leading-tight sm:text-4xl">{{ $course->title }}</h1>
            <p class="mt-3 max-w-2xl text-slate-300">{{ $course->subtitle }}</p>

            <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-300">
                <span class="font-semibold text-amber-400">★ {{ $course->rating ?: 'New' }} <span class="font-normal text-slate-400">({{ $reviews->count() }} reviews)</span></span>
                <span class="inline-flex items-center gap-1.5">@include('partials.icon',['name'=>'users','class'=>'h-4 w-4']) {{ number_format($course->students_count) }} students</span>
                <span class="inline-flex items-center gap-1.5">@include('partials.icon',['name'=>'book-open','class'=>'h-4 w-4']) {{ $course->totalLessons() }} lessons</span>
                <span class="inline-flex items-center gap-1.5">@include('partials.icon',['name'=>'clock','class'=>'h-4 w-4']) {{ intdiv($course->duration_minutes, 60) }}h {{ $course->duration_minutes % 60 }}m</span>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <img src="{{ $course->instructor?->avatarUrl() ?? asset('images/aec-logo.svg') }}" class="h-11 w-11 rounded-full object-cover ring-2 ring-white/20" alt="">
                <div>
                    <p class="text-sm font-bold">{{ $course->instructor?->name ?? "Alom's English Center" }}</p>
                    <p class="text-xs text-slate-400">{{ $course->instructor?->designation ?? 'Instructor' }}</p>
                </div>
            </div>
        </div>

    </div>
</section>

<nav aria-label="Course sections" class="sticky top-[76px] z-30 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
    <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 py-2 text-sm font-bold text-slate-600">
        <a href="#instructor" class="shrink-0 rounded-lg px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800">Instructor</a><a href="#course-format" class="shrink-0 rounded-lg px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800">Course format</a><a href="#outcomes" class="shrink-0 rounded-lg px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800">What you'll learn</a><a href="#syllabus" class="shrink-0 rounded-lg px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800">Syllabus</a><a href="#course-faq" class="shrink-0 rounded-lg px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800">FAQ</a>
    </div>
</nav>

<div class="aec-detail-page mx-auto grid max-w-7xl gap-10 px-4 py-12 lg:grid-cols-[1.6fr_1fr]">
    <div class="order-2 space-y-10 lg:order-1">
        <section id="instructor" class="aec-detail-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-4"><img src="{{ $course->instructor?->avatarUrl() ?? asset('images/aec-logo.svg') }}" class="h-16 w-16 rounded-full object-cover" alt=""><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Your instructor</p><h2 class="mt-1 text-lg font-extrabold text-slate-900">{{ $course->instructor?->name ?? "Alom's English Center" }}</h2><p class="text-sm text-slate-500">{{ $course->instructor?->designation ?? 'English instructor' }}</p></div></div>
            @if($course->instructor?->bio)<p class="mt-4 text-sm leading-7 text-slate-600">{{ $course->instructor->bio }}</p>@endif
        </section>

        <section id="course-format" class="aec-detail-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-extrabold text-slate-900">How this course is structured</h2><p class="mt-2 text-sm text-slate-500">A guided path from lessons to practice and progress.</p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">@foreach([['monitor','Expert-led lessons','Learn key ideas with focused video and class content.'],['clipboard-check','Practice activities','Reinforce learning with course exams and exercises.'],['book-open','Study resources','Use the notes and materials included with lessons.'],['graduation-cap','Progress tracking','Follow your course completion and certificate eligibility.']] as [$icon,$title,$copy])<article class="rounded-2xl border border-slate-100 bg-slate-50 p-4"><span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-100 text-emerald-800">@include('partials.icon',['name'=>$icon])</span><h3 class="mt-3 font-bold text-slate-900">{{ $title }}</h3><p class="mt-1 text-sm leading-6 text-slate-600">{{ $copy }}</p></article>@endforeach</div>
        </section>

        {{-- OUTCOMES --}}
        @if (!empty($course->outcomes))
            <section id="outcomes" class="aec-detail-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-slate-900">What you'll learn</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($course->outcomes as $outcome)
                        <li class="flex gap-2 text-sm text-slate-600"><span class="mt-0.5 text-emerald-700">✓</span>{{ $outcome }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- DESCRIPTION --}}
        <section id="course-overview" class="aec-detail-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-slate-900">Course description</h2>
            <div class="prose prose-sm mt-3 max-w-none whitespace-pre-line text-slate-600">{{ $course->description }}</div>
            @if ($course->requirements)
                <h3 class="mt-6 font-bold text-slate-900">Requirements</h3>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $course->requirements }}</p>
            @endif
        </section>

        {{-- CURRICULUM --}}
        <section id="syllabus" class="aec-detail-panel rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-xl font-bold text-slate-900">Course content</h2>
                <span class="text-sm text-slate-600">{{ $course->sections->count() }} sections · {{ $course->totalLessons() }} lessons</span>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($course->sections as $section)
                    <details class="group" {{ $loop->first ? 'open' : '' }}>
                        <summary class="flex cursor-pointer items-center justify-between gap-3 px-6 py-4 font-semibold text-slate-800 hover:bg-slate-50">
                            <span>{{ $section->title }}</span>
                            <span class="text-xs font-normal text-slate-500">{{ $section->lessons->count() }} lessons</span>
                        </summary>
                        <ul class="divide-y divide-slate-50 border-t border-slate-100">
                            @foreach ($section->lessons as $lesson)
                                <li class="flex items-center gap-3 px-6 py-3 text-sm">
                                    <span class="text-slate-400">
                                        @switch($lesson->type)
                                            @case('video') @include('partials.icon',['name'=>'play','class'=>'h-4 w-4']) @break
                                            @case('pdf') @include('partials.icon',['name'=>'book-open','class'=>'h-4 w-4']) @break
                                            @case('exam') @include('partials.icon',['name'=>'clipboard-check','class'=>'h-4 w-4']) @break
                                            @default @include('partials.icon',['name'=>'book-open','class'=>'h-4 w-4'])
                                        @endswitch
                                    </span>
                                    <span class="flex-1 text-slate-700">{{ $lesson->title }}</span>
                                    @if ($lesson->is_free_preview)
                                        @if($previewUrl = $lesson->embedUrl())<a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700 hover:bg-emerald-200">Free preview</a>@else<span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">Free preview</span>@endif
                                    @endif
                                    <span class="text-xs text-slate-500">{{ $lesson->duration_minutes }}m</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        </section>

        {{-- EXAMS --}}
        @if ($exams->count())
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-slate-900">Exams in this course</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($exams as $exam)
                        <li class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $exam->title }}</p>
                                <p class="text-xs text-slate-500">{{ $exam->duration_minutes }} min · {{ $exam->questions()->count() }} questions · {{ $exam->total_marks }} marks</p>
                            </div>
                            <a href="{{ route('student.exams.show', $exam) }}" class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-emerald-700 shadow-sm ring-1 ring-slate-200 hover:bg-emerald-50">Details</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- REVIEWS --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900">Student reviews</h2>
                <span class="text-sm font-semibold text-amber-700">★ {{ $course->rating ?: '—' }}/5</span>
            </div>

            @auth
                @if ($enrolled)
                    <form method="POST" action="{{ route('reviews.store', $course->slug) }}" class="mt-4 rounded-xl bg-slate-50 p-4">
                        @csrf
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-semibold text-slate-700">Your rating</label>
                            <select name="rating" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                                @for ($i = 5; $i >= 1; $i--)
                                    <option value="{{ $i }}" @selected(optional($myReview)->rating === $i)>{{ $i }} ★</option>
                                @endfor
                            </select>
                        </div>
                        <textarea name="comment" rows="3" placeholder="Share your experience…" class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ optional($myReview)->comment }}</textarea>
                        <input type="url" name="video_url" value="{{ old('video_url', optional($myReview)->video_url) }}" placeholder="Optional YouTube or Vimeo video link" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="mt-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Submit review</button>
                    </form>
                @endif
            @endauth

            <ul class="mt-6 space-y-4">
                @forelse ($reviews as $review)
                    <li class="flex gap-3">
                        <img src="{{ $review->user?->avatarUrl() ?? asset('images/aec-logo.svg') }}" class="h-10 w-10 rounded-full" alt="">
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ $review->user?->name ?? 'Former student' }}</p>
                            <p class="text-xs text-amber-700">{{ str_repeat('★', $review->rating) }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $review->comment }}</p>
                            @if($review->videoEmbedUrl())<a href="{{ $review->videoEmbedUrl() }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-emerald-700">@include('partials.icon',['name'=>'play','class'=>'h-3.5 w-3.5']) Watch student video review</a>@endif
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-600">No reviews yet — be the first to review this course.</li>
                @endforelse
            </ul>
        </section>

        <section id="course-faq" class="aec-detail-panel rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-extrabold text-slate-900">Frequently asked questions</h2>
            <div class="mt-4 divide-y divide-slate-100">@foreach([['Who is this course for?', 'This course is designed for learners at the level shown above. Review the course outline to see if it matches your goals.'],['How do I access the lessons?', 'After enrollment, sign in and open My Courses to continue learning from your dashboard.'],['Will I receive a certificate?', $course->has_certificate ? 'Yes. Eligible learners can receive a certificate after completing the course requirements.' : 'This course currently focuses on learning progress and does not include a completion certificate.']] as [$question,$answer])<details class="group py-4"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold text-slate-800">{{ $question }}<span class="text-xl text-emerald-700 transition group-open:rotate-45">+</span></summary><p class="mt-3 text-sm leading-6 text-slate-600">{{ $answer }}</p></details>@endforeach</div>
        </section>
    </div>

    {{-- SIDEBAR --}}
    <aside class="order-1 space-y-6 lg:order-2">
        <div class="aec-detail-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl lg:sticky lg:top-24">
            <div class="relative bg-slate-950" data-course-preview>
                <img data-course-preview-image src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" class="aspect-video w-full object-cover {{ $trailerEmbed ? '' : 'block' }}">
                @if($trailerEmbed)<iframe data-course-preview-video src="{{ $trailerEmbed }}?rel=0" title="{{ $course->title }} course introduction" class="hidden aspect-video w-full" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe><button type="button" data-course-preview-toggle class="absolute inset-0 grid place-items-center bg-slate-950/15 text-white" aria-label="Play course introduction"><span class="grid h-14 w-14 place-items-center rounded-full bg-emerald-600 shadow-lg">@include('partials.icon',['name'=>'play','class'=>'h-6 w-6'])</span></button>@endif
            </div>
            @if($trailerEmbed)<div class="flex items-center justify-between border-b border-slate-100 px-4 py-2"><span class="text-xs font-semibold text-slate-500">Course preview</span><button type="button" data-course-preview-reset class="text-xs font-bold text-emerald-700 hover:underline">Back to course image</button></div>@endif
            <div class="p-5">
                @if ($course->activeFlashSale())<div class="mb-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800">{{ $course->activeFlashSale()->name }} · Offer ends {{ $course->activeFlashSale()->ends_at->format('d M, h:i A') }}</div>@endif
                <div class="flex items-baseline gap-2"><span class="text-3xl font-extrabold text-slate-900">{{ $course->final_price <= 0 ? 'Free' : '৳'.number_format($course->final_price) }}</span>@if ($course->has_discount)<span class="text-slate-400 line-through">৳{{ number_format($course->price) }}</span><span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">-{{ $course->discount_percent }}%</span>@endif</div>
                @if ($enrolled)<a href="{{ route('student.learn', $course->slug) }}" class="mt-4 block rounded-xl bg-emerald-600 px-4 py-3 text-center font-extrabold text-white hover:bg-emerald-700">Continue learning</a><div class="mt-3"><div class="flex justify-between text-xs font-semibold text-slate-500"><span>Your progress</span><span>{{ $course->progressFor(auth()->user()) }}%</span></div><div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $course->progressFor(auth()->user()) }}%"></div></div></div>
                @elseif ($course->final_price <= 0)<form method="POST" action="{{ route('courses.enroll-free', $course->slug) }}" class="mt-4">@csrf<button class="w-full rounded-xl bg-emerald-600 px-4 py-3 font-extrabold text-white hover:bg-emerald-700">Enroll free</button></form>
                @else<form method="POST" action="{{ route('cart.add', ['course', $course->id]) }}" class="mt-4">@csrf<button class="w-full rounded-xl bg-emerald-600 px-4 py-3 font-extrabold text-white hover:bg-emerald-700">Enroll in this course</button></form>@endif
                <ul class="mt-5 space-y-2 text-sm text-slate-600">@foreach(['Lifetime access to course content','Study notes and resources','Course exams and progress tracking', $course->has_certificate ? 'Certificate on completion' : 'Learn at your own pace'] as $benefit)<li class="flex items-start gap-2"><span class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700">@include('partials.icon',['name'=>'check','class'=>'h-3 w-3'])</span>{{ $benefit }}</li>@endforeach</ul>
                @guest<p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600"><a href="{{ route('login') }}" class="font-semibold text-emerald-700 underline">Log in</a> to enroll in this course.</p>@endguest
                @if($supportPhone = \App\Models\Setting::get('site_phone'))<a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}" class="mt-4 flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-bold text-slate-800">@include('partials.icon',['name'=>'phone','class'=>'h-5 w-5 text-emerald-700'])<span>Need help choosing?<small class="mt-0.5 block text-xs font-normal text-slate-500">Call {{ $supportPhone }}</small></span></a>@endif
            </div>
        </div>
        @if ($course->liveClasses->count())
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-bold text-slate-900">Live classes</h3>
                <ul class="mt-3 space-y-3 text-sm">
                    @foreach ($course->liveClasses as $live)
                        <li class="rounded-xl bg-slate-50 p-3">
                            <p class="font-semibold text-slate-800">{{ $live->title }}</p>
                            <p class="text-xs text-slate-500">{{ optional($live->starts_at)->format('d M Y, h:i A') }} · {{ ucfirst($live->platform) }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

    </aside>
</div>
@if ($related->count())
    <section class="bg-white py-14"><div class="mx-auto max-w-7xl px-4"><div class="flex items-end justify-between gap-3"><div><p class="text-xs font-extrabold uppercase tracking-wider text-emerald-700">Keep learning</p><h2 class="mt-2 text-2xl font-black text-slate-900">Courses picked for you</h2></div><a href="{{ route('courses.index') }}" class="text-sm font-bold text-emerald-700">See all courses →</a></div><div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach($related as $course)<div>@include('partials.course-card',['course'=>$course])</div>@endforeach</div></div></section>
@endif
@push('scripts')<script>(()=>{const preview=document.querySelector('[data-course-preview]');if(!preview)return;const image=preview.querySelector('[data-course-preview-image]'),video=preview.querySelector('[data-course-preview-video]'),toggle=preview.querySelector('[data-course-preview-toggle]'),reset=document.querySelector('[data-course-preview-reset]');if(!video||!toggle)return;const play=()=>{image.classList.add('hidden');video.classList.remove('hidden');toggle.classList.add('hidden');const src=video.getAttribute('src');if(!src.includes('autoplay=1'))video.setAttribute('src',src+(src.includes('?')?'&':'?')+'autoplay=1')};const stop=()=>{video.classList.add('hidden');image.classList.remove('hidden');toggle.classList.remove('hidden');video.setAttribute('src',video.getAttribute('src').replace(/[?&]autoplay=1/,'').replace(/[?&]$/,''))};toggle.addEventListener('click',play);reset?.addEventListener('click',stop)})();</script>@endpush
@endsection
