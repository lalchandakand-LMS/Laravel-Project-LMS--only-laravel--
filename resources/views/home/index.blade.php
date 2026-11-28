@extends('layouts.app')
@section('title', 'English Courses, Spoken English & Grammar')
@section('meta_description', 'Learn spoken English, grammar, vocabulary, writing and communication skills with guided courses at Alom\'s English Center.')
@section('content')
<div id="homepage-sections" data-home-theme="{{ $homepageSettings['homepage_theme_mode'] ?? 'dark' }}" data-home-animation="{{ $homepageSettings['homepage_animation_style'] ?? 'rise' }}" data-home-animation-speed="{{ (int) ($homepageSettings['homepage_animation_speed'] ?? 650) }}">

{{-- HERO SLIDER ONLY --}}
@php($slides = $sliders ?? collect())
@if (($homepageSettings['homepage_show_hero'] ?? '1') === '1')
<section data-home-section="hero" class="home-hero-shell overflow-hidden text-white">
    <div class="mx-auto max-w-7xl px-4 pb-5 pt-8 sm:pt-10">
        @if ($slides->count())
            <div id="heroSlides" class="coverflow-carousel relative mx-auto" tabindex="0" aria-roledescription="carousel" aria-label="Featured learning highlights">
                <div class="coverflow-stage relative mx-auto">
                    @foreach ($slides as $i => $slide)
                        <article class="coverflow-slide {{ $i === 0 ? 'is-active' : '' }}" data-hero-slide data-slide="{{ $i }}" aria-hidden="{{ $i === 0 ? 'false' : 'true' }}">
                            <img src="{{ $slide->imageUrl() ?: asset('images/aec-logo.svg') }}" alt="{{ $slide->title ?: 'Alom’s English Center learning banner' }}" class="h-full w-full rounded-[1.6rem] object-cover">
                        </article>
                    @endforeach
                </div>
                @if ($slides->count() > 1)
                    <button type="button" onclick="heroGo(-1)" aria-label="Previous banner" class="coverflow-arrow left-2 sm:left-[7%]">‹</button>
                    <button type="button" onclick="heroGo(1)" aria-label="Next banner" class="coverflow-arrow right-2 sm:right-[7%]">›</button>
                    <div class="coverflow-dots" aria-label="Choose banner">
                        @foreach ($slides as $i => $slide)
                            <button type="button" onclick="heroShow({{ $i }})" data-hero-dot aria-label="Banner {{ $i + 1 }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="coverflow-stage relative mx-auto max-w-5xl overflow-hidden rounded-[1.6rem] border border-white/10 bg-white/5 shadow-2xl">
                <div class="grid h-full min-h-64 place-items-center"><img src="{{ asset('images/aec-logo.svg') }}" alt="{{ \App\Models\Setting::get('site_name', 'Alom English Center') }} banner placeholder" class="h-32 w-32 object-contain opacity-80"></div>
            </div>
        @endif


    </div>
</section>

@push('scripts')
<style>
.home-hero-shell{background:linear-gradient(125deg,#062c30 0%,#041c24 100%)}
.home-glass-card{border:1px solid rgba(255,255,255,.12);border-radius:1.35rem;background:rgba(255,255,255,.075);padding:1.35rem;box-shadow:0 18px 55px rgba(0,0,0,.16);backdrop-filter:blur(16px)}
.coverflow-carousel{max-width:1120px;padding:0 0 3.25rem}
.coverflow-stage{height:clamp(230px,43vw,490px);overflow:visible;perspective:1200px}
.coverflow-slide{position:absolute;left:9%;top:0;width:82%;height:100%;border:1px solid rgba(255,255,255,.16);border-radius:1.6rem;overflow:hidden;box-shadow:0 28px 70px rgba(0,0,0,.35);opacity:0;pointer-events:none;transform:translateX(0) scale(.78);filter:blur(5px);transition:transform .55s ease,opacity .55s ease,filter .55s ease}
.coverflow-slide.is-active{opacity:1;pointer-events:auto;transform:translateX(0) scale(1);filter:none;z-index:3}
.coverflow-slide.is-prev,.coverflow-slide.is-next{opacity:.62;pointer-events:auto;filter:blur(2px);z-index:2}
.coverflow-slide.is-prev{transform:translateX(-76%) scale(.82)}
.coverflow-slide.is-next{transform:translateX(76%) scale(.82)}
.coverflow-arrow{position:absolute;bottom:3.5rem;z-index:5;display:grid;width:2.65rem;height:2.65rem;place-items:center;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:rgba(255,255,255,.13);color:white;font-size:1.8rem;line-height:1;backdrop-filter:blur(12px)}
.coverflow-dots{position:absolute;bottom:3.8rem;left:50%;z-index:6;display:flex;gap:.5rem;transform:translateX(-50%)}
.coverflow-dots button{width:.48rem;height:.48rem;border-radius:999px;background:rgba(255,255,255,.35);transition:all .2s}
.coverflow-dots button[aria-current=true]{width:1.5rem;background:#22d3ee}
@media(max-width:639px){.coverflow-slide{left:2%;width:96%}.coverflow-slide.is-prev{transform:translateX(-84%) scale(.84)}.coverflow-slide.is-next{transform:translateX(84%) scale(.84)}}
@media(prefers-reduced-motion:reduce){.coverflow-slide{transition:none}}
</style>
<script>
(() => {
 const root=document.getElementById('heroSlides');if(!root)return;
 const slides=[...root.querySelectorAll('[data-hero-slide]')],dots=[...root.querySelectorAll('[data-hero-dot]')];if(!slides.length)return;
 let active=0,timer=null;
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
 const stop=()=>{if(timer)clearInterval(timer);timer=null};
 function render(){
  slides.forEach((slide,i)=>{
   const prev=(active-1+slides.length)%slides.length,next=(active+1)%slides.length;
   slide.classList.toggle('is-active',i===active);slide.classList.toggle('is-prev',slides.length>2&&i===prev);slide.classList.toggle('is-next',slides.length>2&&i===next);
   if(slides.length===2){slide.classList.toggle('is-prev',i===prev);slide.classList.toggle('is-next',i===next);}
   slide.setAttribute('aria-hidden',i===active?'false':'true');
  });
  dots.forEach((dot,i)=>dot.setAttribute('aria-current',i===active?'true':'false'));
 }
 function restart(){stop();if(slides.length>1&&!reduced.matches&&!root.matches(':hover')&&!root.contains(document.activeElement))timer=setInterval(()=>show(active+1),6500)}
 function show(index){active=(index+slides.length)%slides.length;render();restart()}
 window.heroShow=show;window.heroGo=direction=>show(active+direction);
 root.addEventListener('mouseenter',stop);root.addEventListener('mouseleave',restart);root.addEventListener('focusin',stop);root.addEventListener('focusout',e=>{if(!root.contains(e.relatedTarget))restart()});
 root.addEventListener('keydown',e=>{if(e.key==='ArrowRight'){e.preventDefault();show(active+1)}if(e.key==='ArrowLeft'){e.preventDefault();show(active-1)}});
 let x=0,y=0;root.addEventListener('touchstart',e=>{x=e.changedTouches[0].clientX;y=e.changedTouches[0].clientY;stop()},{passive:true});
 root.addEventListener('touchend',e=>{const dx=e.changedTouches[0].clientX-x,dy=e.changedTouches[0].clientY-y;if(Math.abs(dx)>45&&Math.abs(dx)>Math.abs(dy))show(active+(dx<0?1:-1));else restart()},{passive:true});
 reduced.addEventListener('change',restart);render();restart();
})();
</script>
@endpush
@endif

{{-- POST-SLIDER LEARNING HIGHLIGHTS: kept outside the hero so the homepage content begins directly under the slider. --}}
@if (($homepageSettings['homepage_show_online_batches'] ?? '1') === '1')
<section data-home-section="online_batches" class="bg-slate-50 py-8 sm:py-10">
    <div class="mx-auto max-w-7xl px-4">
        <div class="home-highlight-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="inline-flex rounded-full bg-orange-100 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wider text-orange-700">{{ $homepageSettings['homepage_online_batches_badge'] ?? 'Online Course' }}</span>
                    <h2 class="mt-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">{{ $homepageSettings['homepage_online_batches_title'] ?? 'Online Batches are ongoing!' }}</h2>
                </div>
                <a href="{{ route('courses.index') }}" class="inline-flex items-center rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-orange-600">See All Courses →</a>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($onlineBatches->take(4) as $batch)
                    <a href="{{ route('courses.index') }}" class="group flex min-w-0 items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:-translate-y-0.5 hover:border-orange-300 hover:bg-orange-50">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-orange-100 text-xl text-orange-700">▣</span>
                        <span class="min-w-0"><strong class="block truncate text-sm font-extrabold text-slate-900">{{ $batch->name }}</strong><small class="mt-1 block truncate text-xs text-slate-500">{{ $batch->course?->title ?: 'Online learning batch' }}</small></span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500 sm:col-span-2 lg:col-span-4">New online batches will be announced soon.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endif

@if (($homepageSettings['homepage_show_stats'] ?? '1') === '1')
<section data-home-section="stats" class="bg-white py-8 sm:py-10">
    <div class="mx-auto max-w-7xl px-4">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">{{ $homepageSettings['homepage_stats_title'] ?? 'See the stats!' }}</h2>
            <a href="{{ route('courses.index') }}" class="text-sm font-bold text-orange-600 hover:text-orange-700">See All Courses →</a>
        </div>
        <div class="grid grid-cols-2 gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-4 sm:gap-5 sm:p-7">
            @foreach ([
                [$homepageSettings['homepage_stats_students_label'] ?? 'Students', $stats['students'], $homepageSettings['homepage_stats_students_icon'] ?? '♙'],
                [$homepageSettings['homepage_stats_courses_label'] ?? 'Courses', $stats['courses'], $homepageSettings['homepage_stats_courses_icon'] ?? '▤'],
                [$homepageSettings['homepage_stats_teachers_label'] ?? 'Teachers', $stats['instructors'], $homepageSettings['homepage_stats_teachers_icon'] ?? '♧'],
                [$homepageSettings['homepage_stats_lessons_label'] ?? 'Videos / Lessons', $stats['lessons'], $homepageSettings['homepage_stats_lessons_icon'] ?? '▷'],
            ] as [$label, $value, $icon])
                <div class="rounded-2xl bg-slate-50 p-4 text-center sm:p-5">
                    <span class="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-orange-100 text-xl text-orange-700">{{ $icon }}</span>
                    <strong class="mt-3 block text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ number_format($value) }}+</strong>
                    <p class="mt-1 text-xs font-bold text-slate-500">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- LEARNING PATH CATEGORIES --}}
@if (($homepageSettings['homepage_show_categories'] ?? '1') === '1')
<section data-home-section="categories" data-home-carousel="categories" data-home-carousel-layout="{{ $homepageSettings['homepage_learning_layout'] ?? 'grid' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}" class="learning-path-section py-16" style="background:{{ $homepageSettings['homepage_learning_bg'] ?? '#f1f3ff' }}">
    <div class="mx-auto max-w-7xl px-4">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-xs font-extrabold uppercase tracking-[.22em] text-teal-600">Explore your interests</p>
            <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-cyan-800 sm:text-4xl">{{ $homepageSettings['homepage_learning_title'] ?? 'Choose your learning path' }}</h2>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $homepageSettings['homepage_learning_subtitle'] ?? 'Choose a course to improve speaking, grammar, vocabulary, writing or IELTS skills.' }}</p>
        </div>
        @php($categoryPalettes = [['from-sky-100 to-blue-50','bg-sky-500'],['from-emerald-100 to-green-50','bg-emerald-500'],['from-violet-100 to-purple-50','bg-violet-500'],['from-orange-100 to-amber-50','bg-[#22d3ee]'],['from-pink-100 to-rose-50','bg-pink-500']])
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($categories as $index => $category)
                @php([$gradient, $iconColor] = $categoryPalettes[$index % count($categoryPalettes)])
                <a href="{{ route('categories.show', $category->slug) }}" class="group rounded-2xl border border-white/80 bg-gradient-to-br {{ $gradient }} p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid h-12 w-12 place-items-center rounded-xl {{ $iconColor }} text-2xl text-white shadow-sm">{{ $category->icon ?: '📘' }}</span>
                        <span class="text-sm font-extrabold text-slate-600 transition group-hover:translate-x-1">Explore ›</span>
                    </div>
                    <h3 class="mt-5 text-lg font-extrabold text-slate-900">{{ $category->name }}</h3>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $category->description }}</p>
                    <span class="mt-4 inline-block text-xs font-bold text-slate-600">{{ $category->published_courses_count ?? $category->publishedCourses()->count() }} courses</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- FEATURED --}}
@if (($homepageSettings['homepage_show_featured'] ?? '0') === '1' && $featured->count())
<section data-home-section="featured" data-home-carousel="featured" data-home-carousel-layout="{{ $homepageSettings['homepage_featured_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}"  class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-4">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_featured_title'] ?? 'Featured courses' }}</h2>
        <p class="mt-1 text-slate-500">{{ $homepageSettings['homepage_featured_subtitle'] ?? 'Hand-picked by our academic team.' }}</p>
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($featured as $course)
                @include('partials.course-card', ['course' => $course])
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- Online batches and statistics are displayed inside the hero section above. --}}

{{-- LATEST --}}
@if (($homepageSettings['homepage_show_latest'] ?? '0') === '1')
<section data-home-section="latest" data-home-carousel="latest" data-home-carousel-layout="{{ $homepageSettings['homepage_latest_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}"  class="mx-auto max-w-7xl px-4 py-16">
    <div class="flex items-end justify-between">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_latest_title'] ?? 'Latest courses' }}</h2>
        <div class="text-right"><p class="mt-1 text-sm text-slate-500">{{ $homepageSettings['homepage_latest_subtitle'] ?? 'Explore our newest learning opportunities.' }}</p><a href="{{ route('courses.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">Browse all →</a></div>
    </div>
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($latest as $course)
            @include('partials.course-card', ['course' => $course])
        @endforeach
    </div>
</section>
@endif

{{-- UPCOMING LIVE CLASSES --}}
@if (($homepageSettings['homepage_show_live_classes'] ?? '0') === '1' && $upcomingLiveClasses->isNotEmpty())
<section data-home-section="live_classes" data-home-carousel="live_classes" data-home-carousel-layout="{{ $homepageSettings['homepage_live_classes_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}"  class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_live_classes_title'] ?? 'Upcoming live classes' }}</h2><p class="mt-1 text-slate-500">{{ $homepageSettings['homepage_live_classes_subtitle'] ?? 'Join scheduled learning sessions with our instructors.' }}</p></div>
            <a href="{{ route('courses.index') }}" class="text-sm font-bold text-emerald-700">Explore courses →</a>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($upcomingLiveClasses as $class)
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-center justify-between gap-2"><span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">{{ $class->platform }}</span><span class="text-xs font-semibold text-slate-500">{{ $class->starts_at->format('d M Y') }}</span></div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900">{{ $class->title }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $class->course?->title }}</p>
                    <p class="mt-3 text-sm text-slate-600">{{ $class->starts_at->format('h:i A') }} · {{ $class->duration_minutes }} minutes</p>
                    <a href="{{ $class->course ? route('courses.show', $class->course->slug) : route('courses.index') }}" class="mt-4 inline-flex text-sm font-bold text-emerald-700">View course details →</a>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- UPCOMING EXAMS --}}
@if (($homepageSettings['homepage_show_exams'] ?? '0') === '1' && $upcomingExams->isNotEmpty())
<section data-home-section="exams" data-home-carousel="exams" data-home-carousel-layout="{{ $homepageSettings['homepage_exams_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}"  class="mx-auto max-w-7xl px-4 py-16">
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_exams_title'] ?? 'Exams & assessments' }}</h2><p class="mt-1 text-slate-500">{{ $homepageSettings['homepage_exams_subtitle'] ?? 'Prepare, practise and track your progress.' }}</p></div><a href="{{ route('login') }}" class="text-sm font-bold text-emerald-700">Student login →</a></div>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($upcomingExams as $exam)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $exam->course?->title }}</span>
                <h3 class="mt-2 font-bold text-slate-900">{{ $exam->title }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ $exam->total_marks }} marks · {{ $exam->duration_minutes }} minutes</p>
                <p class="mt-1 text-xs text-slate-500">{{ $exam->starts_at ? $exam->starts_at->format('d M Y, h:i A') : 'Schedule announced in student portal' }}</p>
            </article>
        @endforeach
    </div>
</section>
@endif

{{-- TEACHER PROFILES --}}
@if (($homepageSettings['homepage_show_teachers'] ?? '0') === '1' && $teachers->isNotEmpty())
<section data-home-section="teachers" data-home-carousel="teachers" data-home-carousel-layout="{{ $homepageSettings['homepage_teachers_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}"  id="teachers" class="bg-slate-50 py-16">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex flex-wrap items-end justify-between gap-4"><div class="text-center sm:text-left"><h2 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_teachers_title'] ?? 'Meet our teachers' }}</h2><p class="mt-1 text-slate-500">{{ $homepageSettings['homepage_teachers_subtitle'] ?? 'Learn with experienced instructors and practical guidance.' }}</p></div><a href="{{ route('instructors.index') }}" class="rounded-full border border-blue-200 bg-white px-5 py-2.5 text-sm font-bold text-blue-800 shadow-sm transition hover:bg-blue-50">View all instructors →</a></div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($teachers as $teacher)
                <article class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                    <img src="{{ $teacher->avatarUrl() }}" alt="{{ $teacher->name }}" class="mx-auto h-20 w-20 rounded-full object-cover ring-4 ring-emerald-50">
                    <h3 class="mt-4 font-bold text-slate-900"><a href="{{ route('instructors.show', $teacher) }}" class="hover:text-blue-700">{{ $teacher->name }}</a></h3>
                    @if ($teacher->designation)<p class="mt-1 text-sm font-semibold text-emerald-700">{{ $teacher->designation }}</p>@endif
                    @if ($teacher->bio)<p class="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-600">{{ $teacher->bio }}</p>@endif
                    <p class="mt-3 text-xs text-slate-500">{{ $teacher->courses_count }} published learning courses</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- VERIFIED BOARD RESULTS / ACHIEVEMENTS --}}
@if (($homepageSettings['homepage_show_board_results'] ?? '0') === '1' && !empty($homepageSettings['homepage_board_results_body']))
<section data-home-section="board_results" id="board-results" class="bg-emerald-950 py-16 text-white">
    <div class="mx-auto max-w-5xl px-4 text-center">
        <span class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Student achievements {{ $homepageSettings['homepage_board_results_year'] ?? '' }}</span>
        <h2 class="mt-3 text-3xl font-extrabold">{{ $homepageSettings['homepage_board_results_title'] ?? 'Our students’ achievements' }}</h2>
        @if (!empty($homepageSettings['homepage_board_results_subtitle']))<p class="mt-2 text-emerald-100">{{ $homepageSettings['homepage_board_results_subtitle'] }}</p>@endif
        <div class="mx-auto mt-6 max-w-3xl whitespace-pre-line text-base leading-8 text-emerald-50">{{ $homepageSettings['homepage_board_results_body'] }}</div>
    </div>
</section>
@endif

{{-- STUDENT TESTIMONIALS --}}
@if (($homepageSettings['homepage_show_reviews'] ?? '1') === '1' && $reviews->count())
<section data-home-section="reviews" class="student-testimonials py-16 text-white">
    <div class="mx-auto max-w-7xl px-4">
        <div class="text-center">
            <p class="text-xs font-extrabold uppercase tracking-[.22em] text-cyan-300">Student stories</p>
            <h2 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $homepageSettings['homepage_testimonial_title'] ?? 'What Our Students Say' }}</h2>
            <p class="mt-3 text-sm text-slate-300">{{ $homepageSettings['homepage_reviews_subtitle'] ?? 'Real feedback from our learning community.' }}</p>
            <div class="mt-4 inline-flex items-center gap-2 rounded-full border border-amber-300/20 bg-amber-300/10 px-4 py-2 text-sm font-bold text-amber-200"><span>★★★★★</span><strong>{{ number_format((float) $reviews->avg('rating'), 1) }}</strong><span class="text-slate-300">Average rating</span></div>
        </div>
        <div class="mx-auto mt-9 grid max-w-6xl gap-4 sm:grid-cols-2 lg:grid-cols-3 {{ $reviews->count() === 1 ? 'max-w-xl' : '' }}">
            @foreach ($reviews as $review)
                <figure class="flex h-full flex-col rounded-2xl border border-white/10 bg-slate-800/90 p-5 shadow-xl">
                    <div class="flex items-center justify-between"><span class="text-sm tracking-wider text-amber-300">{{ str_repeat('★', $review->rating) }}<span class="text-slate-500">{{ str_repeat('★', 5 - $review->rating) }}</span></span><span class="text-3xl leading-none text-cyan-300/70">“</span></div>
                    <blockquote class="mt-2 flex-1 text-sm leading-relaxed text-slate-200">“{{ $review->comment }}”</blockquote>
                    <figcaption class="mt-5 flex items-center gap-3 border-t border-white/10 pt-4">
                        <img src="{{ $review->user?->avatarUrl() ?? asset('images/aec-logo.svg') }}" class="h-10 w-10 rounded-full object-cover" alt="">
                        <div class="min-w-0"><p class="truncate text-sm font-bold text-white">{{ $review->user?->name ?? 'Former student' }}</p><p class="text-xs font-semibold text-cyan-300">@if ($review->user && $review->course && \App\Services\EnrollmentEligibility::hasCourse($review->user, $review->course)) ✓ Verified Student @else Student Review @endif</p></div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@push('scripts')
<style>
.student-testimonials{background:linear-gradient(135deg,#111827 0%,#172554 55%,#0f172a 100%)}
</style>
@endpush
@endif

{{-- EBOOKS --}}
@if (($homepageSettings['homepage_show_ebooks'] ?? '0') === '1' && $ebooks->count())
<section data-home-section="ebooks" data-home-carousel="ebooks" data-home-carousel-layout="{{ $homepageSettings['homepage_ebooks_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}"  class="mx-auto max-w-7xl px-4 py-16">
    <div class="flex items-end justify-between">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_ebooks_title'] ?? 'E-books & notes' }}</h2><p class="mt-1 text-sm text-slate-500">{{ $homepageSettings['homepage_ebooks_subtitle'] ?? 'Study guides and resources for learning anywhere.' }}</p>
        <a href="{{ route('ebooks.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">All e-books →</a>
    </div>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($ebooks as $ebook)
            <a href="{{ route('ebooks.show', $ebook->slug) }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm hover:shadow-md">
                <img src="{{ $ebook->coverUrl() }}" class="h-48 w-full object-cover" alt="">
                <div class="p-4">
                    <h3 class="line-clamp-2 font-bold text-slate-900 group-hover:text-emerald-700">{{ $ebook->title }}</h3>
                    <p class="mt-1 text-sm font-semibold text-emerald-700">{{ $ebook->is_free ? 'Free' : '৳'.number_format($ebook->price) }}</p>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- ONLINE / CLASSROOM ADMISSION --}}
@if (($homepageSettings['homepage_show_admission'] ?? '0') === '1')
<section data-home-section="admission" class="mx-auto max-w-7xl px-4 pb-8 pt-4">
    <div class="relative overflow-hidden rounded-3xl bg-slate-900 px-8 py-14 text-center text-white sm:px-12">
        <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-emerald-500/20 blur-3xl"></div>
        <span class="relative inline-flex rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-bold text-emerald-300">Admissions & enrolment</span>
        <h2 class="relative mt-4 text-3xl font-extrabold">{{ $homepageSettings['homepage_admission_title'] ?? 'Join an online or classroom batch' }}</h2>
        <p class="relative mx-auto mt-3 max-w-2xl text-slate-300">{{ $homepageSettings['homepage_admission_subtitle'] ?? 'Choose a course and start learning with Alom’s English Center.' }}</p>
        <a href="{{ $homepageSettings['homepage_admission_button_url'] ?? '/courses' }}" class="relative mt-7 inline-block rounded-xl bg-emerald-500 px-7 py-3 font-bold text-slate-950 hover:bg-emerald-400">{{ $homepageSettings['homepage_admission_button_text'] ?? 'Apply for admission' }}</a>
    </div>
</section>
@endif


</div>

@push('scripts')
<style>
#homepage-sections[data-home-theme="dark"]{background:#08111f;color:#e5edf8}
#homepage-sections[data-home-theme="dark"] [data-home-section]:not([data-home-section="hero"]){background-color:#0f1b2d!important;color:#e5edf8}
#homepage-sections[data-home-theme="dark"] [data-home-section]:not([data-home-section="hero"]) :is(h1,h2,h3,h4,.text-slate-900,.text-slate-800,.text-gray-900,.text-gray-800){color:#f8fafc}
#homepage-sections[data-home-theme="dark"] [data-home-section]:not([data-home-section="hero"]) :is(p,.text-slate-600,.text-slate-500,.text-gray-600,.text-gray-500){color:#cbd5e1}
#homepage-sections[data-home-theme="dark"] [data-home-section]:not([data-home-section="hero"]) :is(.bg-white,.bg-slate-50,.bg-gray-50){background-color:#142238}
#homepage-sections[data-home-theme="dark"] [data-home-section]:not([data-home-section="hero"]) :is(.border-slate-200,.border-gray-200){border-color:#2b3b52}
#homepage-sections[data-home-animation]:not([data-home-animation="none"]) [data-home-reveal]{opacity:0;transition:opacity var(--aec-reveal-duration,650ms) ease,transform var(--aec-reveal-duration,650ms) cubic-bezier(.2,.7,.2,1);will-change:opacity,transform}
#homepage-sections[data-home-animation="rise"] [data-home-reveal]{transform:translateY(28px)}
#homepage-sections[data-home-animation="slide"] [data-home-reveal]{transform:translateX(32px)}
#homepage-sections[data-home-animation="zoom"] [data-home-reveal]{transform:scale(.96)}
#homepage-sections[data-home-animation="fade"] [data-home-reveal]{transform:none}
#homepage-sections [data-home-reveal].is-visible{opacity:1;transform:none}
@media(prefers-reduced-motion:reduce){#homepage-sections [data-home-reveal]{opacity:1!important;transform:none!important;transition:none!important}}
</style>
<script>
(() => {
 const root=document.getElementById('homepage-sections');if(!root)return;
 const style=root.dataset.homeAnimation||'rise';
 const duration=Math.min(1200,Math.max(200,Number(root.dataset.homeAnimationSpeed)||650));
 root.style.setProperty('--aec-reveal-duration',duration+'ms');
 const sections=[...root.querySelectorAll(':scope > [data-home-section]:not([data-home-section="hero"])')];
 if(style==='none'||!('IntersectionObserver' in window)||window.matchMedia('(prefers-reduced-motion: reduce)').matches){sections.forEach(s=>s.classList.add('is-visible'));return;}
 sections.forEach(s=>s.setAttribute('data-home-reveal',''));
 const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}}),{threshold:.08,rootMargin:'0px 0px -35px 0px'});
 sections.forEach(s=>observer.observe(s));
})();
</script>
@endpush

@push('scripts')
<script>
(() => {
    const container = document.getElementById('homepage-sections');
    if (!container) return;
    const sections = new Map([...container.querySelectorAll(':scope > [data-home-section]')].map(section => [section.dataset.homeSection, section]));
    const savedOrder = @json(json_decode($homepageSettings['homepage_section_order'] ?? '[]', true) ?: []);
    [...savedOrder, ...sections.keys()].forEach(key => {
        const section = sections.get(key);
        if (section) { container.appendChild(section); sections.delete(key); }
    });
    for (const section of sections.values()) container.appendChild(section);
})();
</script>
@endpush

@push('scripts')
<style>
.home-carousel-track{scrollbar-width:thin;scroll-behavior:smooth;scroll-snap-type:x mandatory}
.home-carousel-track>*{scroll-snap-align:start;flex:0 0 84%;min-width:0}
@media(min-width:640px){.home-carousel-track>*{flex-basis:48%}}
@media(min-width:1024px){.home-carousel-track>*{flex-basis:31%}}
.home-carousel-controls button:disabled{opacity:.35;cursor:not-allowed}
@media(prefers-reduced-motion:reduce){.home-carousel-track{scroll-behavior:auto}}
</style>
<script>
(() => {
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
 document.querySelectorAll('[data-home-carousel]').forEach(section=>{
  if(section.dataset.homeCarouselLayout!=='carousel')return;
  const track=section.querySelector('.mt-8.grid');if(!track||track.children.length<2)return;
  track.classList.remove('grid','sm:grid-cols-2','md:grid-cols-3','lg:grid-cols-3','lg:grid-cols-4','gap-4','gap-5','gap-6');
  track.classList.add('home-carousel-track','flex','gap-5','overflow-x-auto','pb-3');
  const controls=document.createElement('div');controls.className='home-carousel-controls ml-auto flex shrink-0 gap-2';
  controls.innerHTML='<button type="button" aria-label="Previous items" class="grid h-10 w-10 place-items-center rounded-full border border-slate-300 bg-white text-xl text-slate-700 shadow-sm">‹</button><button type="button" aria-label="Next items" class="grid h-10 w-10 place-items-center rounded-full border border-slate-300 bg-white text-xl text-slate-700 shadow-sm">›</button>';
  const heading=section.querySelector('.mx-auto.max-w-7xl>div.flex.items-end.justify-between, .mx-auto.max-w-7xl>div.flex.flex-wrap.items-end.justify-between');
  if(heading){heading.classList.add('gap-4');heading.appendChild(controls);}else{section.querySelector('.mx-auto.max-w-7xl')?.prepend(controls);}
  const [prev,next]=controls.querySelectorAll('button');
  const step=()=>Math.max(track.clientWidth*.82,220);
  const move=direction=>track.scrollBy({left:direction*step(),behavior:reduced.matches?'auto':'smooth'});
  prev.addEventListener('click',()=>move(-1));next.addEventListener('click',()=>move(1));
  const update=()=>{prev.disabled=track.scrollLeft<=2;next.disabled=track.scrollLeft+track.clientWidth>=track.scrollWidth-2;};
  track.addEventListener('scroll',update,{passive:true});window.addEventListener('resize',update);update();
  track.setAttribute('aria-label',section.dataset.homeCarousel.replace('_',' ')+' carousel');
  let timer=null;
  const stop=()=>{if(timer)window.clearInterval(timer);timer=null;};
  const start=()=>{stop();if(section.dataset.homeCarouselAutoplay!=='1'||reduced.matches||track.scrollWidth<=track.clientWidth+2)return;const delay=Math.min(20,Math.max(3,Number(section.dataset.homeCarouselSpeed)||6))*1000;timer=window.setInterval(()=>{if(section.matches(':hover')||section.contains(document.activeElement))return;move(track.scrollLeft+track.clientWidth>=track.scrollWidth-3?-1:1);},delay);};
  section.addEventListener('mouseenter',stop);section.addEventListener('mouseleave',start);
  section.addEventListener('focusin',stop);section.addEventListener('focusout',event=>{if(!section.contains(event.relatedTarget))start();});
  reduced.addEventListener('change',start);start();
 });
})();
</script>
@endpush

@endsection
