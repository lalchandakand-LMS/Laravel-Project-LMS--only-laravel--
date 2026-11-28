@extends('layouts.app')
@section('title', 'English Courses, Spoken English & Grammar')
@section('meta_description', 'Learn spoken English, grammar, vocabulary, writing and communication skills with guided courses at Alom\'s English Center.')
@section('content')
@php($homepageSectionOrder = \App\Support\HomepageSectionOrder::normalize($homepageSettings['homepage_section_order'] ?? null))
<style>
    #homepage-sections { display: flex; flex-direction: column; }
    #homepage-sections > [data-home-section] { width: 100%; }
    @foreach ($homepageSectionOrder as $sectionPosition => $sectionKey)
        #homepage-sections > [data-home-section="{{ $sectionKey }}"] { order: {{ $sectionPosition }}; }
    @endforeach
</style>
<div id="homepage-sections" class="aec-reference-home" data-home-theme="{{ $homepageSettings['homepage_theme_mode'] ?? 'dark' }}" data-home-animation="{{ $homepageSettings['homepage_animation_style'] ?? 'rise' }}" data-home-animation-speed="{{ (int) ($homepageSettings['homepage_animation_speed'] ?? 650) }}">

{{-- EXISTING HERO SLIDER — preserved from main without changing its design or controls. --}}
@php($slides = $sliders ?? collect())
@if (($homepageSettings['homepage_show_hero'] ?? '1') === '1')
<section data-home-section="hero" class="home-hero-shell overflow-hidden text-white">
    <div class="mx-auto max-w-7xl px-4 pb-10 pt-8 sm:pt-12">
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
<section data-home-section="online_batches" class="py-8 sm:py-10" style="background-color:{{ $homepageSettings['homepage_online_batches_bg'] ?? '#fff7ed' }};--home-section-text:{{ $homepageSettings['homepage_online_batches_text'] ?? '#0f172a' }}">
    <div class="mx-auto max-w-7xl px-4">
        <div class="home-highlight-panel rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wider text-emerald-800">{{ $homepageSettings['homepage_online_batches_badge'] ?? 'Online Course' }}</span>
                    <h2 class="mt-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">{{ $homepageSettings['homepage_online_batches_title'] ?? 'Online Batches are ongoing!' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $homepageSettings['homepage_online_batches_subtitle'] ?? 'Book your seat in an upcoming learning batch.' }}</p>
                </div>
                <a href="{{ route('courses.index') }}" class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">See All Courses →</a>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($onlineBatches->take(4) as $batch)
                    <a href="{{ route('courses.index') }}" class="group flex min-w-0 items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-50">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700">@include('partials.icon',['name'=>'book-open'])</span>
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

{{-- LEARNING BENEFITS --}}
@if (($homepageSettings['homepage_show_learning_benefits'] ?? '1') === '1')
<section data-home-section="learning_benefits" class="py-14 sm:py-20" style="background-color:#f3f8f7">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-2xl">
                <p class="text-xs font-extrabold uppercase tracking-[.2em] text-emerald-700">A complete learning experience</p>
                <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">{{ $homepageSettings['homepage_learning_benefits_title'] ?? 'Everything you need to keep improving' }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $homepageSettings['homepage_learning_benefits_subtitle'] ?? 'Learn, practise and check your progress with the tools available in our LMS.' }}</p>
            </div>
            <a href="{{ route('courses.index') }}" class="rounded-xl border border-emerald-800/15 bg-white px-4 py-2.5 text-sm font-extrabold text-emerald-800 shadow-sm hover:bg-emerald-50">Explore learning →</a>
        </div>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['video', 'Recorded lessons', 'Review course lessons at a pace that works for you.', 'from-cyan-100 to-sky-50', 'text-cyan-800'],
                ['monitor', 'Live classes', 'Join published live sessions when they are scheduled.', 'from-emerald-100 to-green-50', 'text-emerald-800'],
                ['clipboard-check', 'Practice & exams', 'Use assessments in your enrolled course to track learning.', 'from-violet-100 to-purple-50', 'text-violet-800'],
                ['book-open', 'Study resources', 'Find course materials and e-books from the LMS library.', 'from-amber-100 to-emerald-50', 'text-amber-800'],
                ['graduation-cap', 'Teacher guidance', 'Learn with instructors and follow a structured course path.', 'from-rose-100 to-pink-50', 'text-rose-800'],
            ] as [$icon, $title, $description, $palette, $tone])
                <article class="rounded-3xl border border-white bg-gradient-to-br {{ $palette }} p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/80 {{ $tone }} shadow-sm">@include('partials.icon',['name'=>$icon,'class'=>'h-6 w-6'])</span>
                    <h3 class="mt-5 font-extrabold text-slate-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@if (($homepageSettings['homepage_show_learning_preview'] ?? '1') === '1')
<section data-home-section="learning_preview" class="overflow-hidden bg-[#f5f8f6] py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 lg:grid-cols-[1fr_1fr]">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-[.2em] text-emerald-700">Learning preview</p>
            <h2 class="mt-3 max-w-xl text-3xl font-black leading-tight tracking-tight text-slate-950 sm:text-4xl">{{ $homepageSettings['homepage_learning_preview_title'] ?? 'A flexible learning experience, built around your day.' }}</h2>
            <p class="mt-4 max-w-xl text-sm leading-7 text-slate-600">{{ $homepageSettings['homepage_learning_preview_subtitle'] ?? 'Explore online courses, revisit lessons when you need them and keep your learning moving from any device.' }}</p>
            <div class="mt-6 flex flex-wrap gap-3"><a href="{{ route('courses.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-extrabold text-white hover:bg-emerald-700">Browse online courses @include('partials.icon',['name'=>'arrow-right'])</a>@if($previewPhone = \App\Models\Setting::get('site_phone'))<a href="tel:{{ preg_replace('/[^0-9+]/', '', $previewPhone) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-800">@include('partials.icon',['name'=>'phone']) Get learning support</a>@endif</div>
        </div>
        @php($previewCourse = $featured->first() ?? $latest->first())
        <a href="{{ $previewCourse ? route('courses.show', $previewCourse->slug) : route('courses.index') }}" class="group relative overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white p-3 shadow-xl transition hover:-translate-y-1">
            @if($previewCourse)<img src="{{ $previewCourse->thumbnail_url }}" alt="{{ $previewCourse->title }} course preview" loading="lazy" class="aspect-video w-full rounded-[1.25rem] object-cover">@else<div class="grid aspect-video place-items-center rounded-[1.25rem] bg-gradient-to-br from-emerald-100 to-sky-100">@include('partials.icon',['name'=>'book-open','class'=>'h-16 w-16 text-emerald-700'])</div>@endif
            <span class="absolute left-6 top-6 grid h-12 w-12 place-items-center rounded-full bg-white text-emerald-700 shadow-lg transition group-hover:scale-105">@include('partials.icon',['name'=>'play','class'=>'h-5 w-5'])</span>
            <div class="p-4"><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Start learning today</p><strong class="mt-1 block text-lg font-extrabold text-slate-900">{{ $previewCourse?->title ?? 'Explore our learning library' }}</strong><span class="mt-2 inline-flex items-center gap-1 text-sm font-bold text-emerald-700">View course @include('partials.icon',['name'=>'arrow-right','class'=>'h-4 w-4'])</span></div>
        </a>
    </div>
</section>
@endif

@if (($homepageSettings['homepage_show_stats'] ?? '1') === '1')
<section data-home-section="stats" class="home-stats-section bg-white py-8 sm:py-10" style="background:{{ $homepageSettings['homepage_stats_bg'] ?? '#0b1221' }};--home-section-text:{{ $homepageSettings['homepage_stats_text'] ?? '#ffffff' }}">
    <div class="mx-auto max-w-7xl px-4">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl" style="color:var(--home-section-text)">{{ $homepageSettings['homepage_stats_title'] ?? 'See the stats!' }}</h2>
            <a href="{{ route('courses.index') }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-800">See All Courses →</a>
        </div>
        <div class="grid grid-cols-2 gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-4 sm:gap-5 sm:p-7">
            @foreach ([
                [$homepageSettings['homepage_stats_students_label'] ?? 'Students', $stats['students'], $homepageSettings['homepage_stats_students_icon'] ?? 'users'],
                [$homepageSettings['homepage_stats_courses_label'] ?? 'Courses', $stats['courses'], $homepageSettings['homepage_stats_courses_icon'] ?? 'book-open'],
                [$homepageSettings['homepage_stats_teachers_label'] ?? 'Teachers', $stats['instructors'], $homepageSettings['homepage_stats_teachers_icon'] ?? 'graduation-cap'],
                [$homepageSettings['homepage_stats_lessons_label'] ?? 'Videos / Lessons', $stats['lessons'], $homepageSettings['homepage_stats_lessons_icon'] ?? 'video'],
            ] as [$label, $value, $icon])
                <div class="rounded-2xl bg-slate-50 p-4 text-center sm:p-5">
                    <span class="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-emerald-100 text-emerald-700">@include('partials.icon',['name'=>$icon])</span>
                    <strong class="mt-3 block text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ number_format($value) }}{{ $value > 0 ? '+' : '' }}</strong>
                    <p class="mt-1 text-xs font-bold text-slate-500">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- LEARNING PATH CATEGORIES --}}
@if (($homepageSettings['homepage_show_categories'] ?? '1') === '1')
<section data-home-section="categories" data-home-carousel="categories" data-home-carousel-layout="{{ $homepageSettings['homepage_learning_layout'] ?? 'grid' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}" class="learning-path-section py-16" style="background:{{ $homepageSettings['homepage_learning_bg'] ?? '#f1f3ff' }};--home-section-text:{{ $homepageSettings['homepage_learning_text'] ?? '#0f172a' }}">
    <div class="mx-auto max-w-7xl px-4">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-xs font-extrabold uppercase tracking-[.22em] text-teal-600">Explore your interests</p>
            <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-cyan-800 sm:text-4xl">{{ $homepageSettings['homepage_learning_title'] ?? 'Choose your learning path' }}</h2>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $homepageSettings['homepage_learning_subtitle'] ?? 'Choose a course to improve speaking, grammar, vocabulary, writing or IELTS skills.' }}</p>
        </div>
        @php($categoryPalettes = [['from-sky-100 to-blue-50','bg-sky-500'],['from-emerald-100 to-green-50','bg-emerald-500'],['from-violet-100 to-purple-50','bg-violet-500'],['from-emerald-100 to-amber-50','bg-emerald-600'],['from-pink-100 to-rose-50','bg-pink-500']])
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($categories as $index => $category)
                @php([$gradient, $iconColor] = $categoryPalettes[$index % count($categoryPalettes)])
                @php($categoryCourse = $featured->firstWhere('category_id', $category->id) ?? $latest->firstWhere('category_id', $category->id))
                <a href="{{ route('categories.show', $category->slug) }}" class="home-category-card group relative min-h-[190px] overflow-hidden rounded-3xl border border-white/80 bg-gradient-to-br {{ $gradient }} p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg sm:p-6">
                    @if ($categoryCourse)
                        <img src="{{ $categoryCourse->thumbnail_url }}" alt="" loading="lazy" aria-hidden="true" class="absolute inset-0 z-0 h-full w-full object-cover opacity-20 transition duration-300 group-hover:opacity-30">
                        <span aria-hidden="true" class="absolute inset-0 z-[1] bg-gradient-to-br from-white/75 via-white/50 to-white/10"></span>
                    @endif
                    <div class="relative z-10">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid h-12 w-12 place-items-center rounded-xl {{ $iconColor }} text-white shadow-sm">@include('partials.icon',['name'=>'book-open'])</span>
                        <span class="text-sm font-extrabold text-slate-600 transition group-hover:translate-x-1">Explore ›</span>
                    </div>
                    <h3 class="mt-5 text-lg font-extrabold text-slate-900">{{ $category->name }}</h3>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $category->description }}</p>
                    <span class="mt-4 inline-block text-xs font-bold text-slate-600">{{ ($category->direct_published_courses_count ?? 0) + ($category->additional_published_courses_count ?? 0) }} courses</span>
                    </div>
                </a>
            @empty
                <div class="home-highlight-panel rounded-3xl border border-slate-200 bg-white p-6 text-center sm:col-span-2 lg:col-span-3">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-100 text-emerald-700">@include('partials.icon',['name'=>'book-open','class'=>'h-7 w-7'])</span>
                    <h3 class="mt-4 text-lg font-extrabold text-slate-900">Learning paths are being prepared</h3>
                    <p class="mt-1 text-sm text-slate-600">Browse the available courses and choose a place to start.</p>
                    <a href="{{ route('courses.index') }}" class="mt-4 inline-flex rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Browse courses →</a>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endif

{{-- ONLINE AND CLASSROOM LEARNING MODES --}}
@if (($homepageSettings['homepage_show_learning_modes'] ?? '1') === '1')
<section data-home-section="learning_modes" class="overflow-hidden py-14 text-white sm:py-20" style="background-color:#0b1221">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 lg:grid-cols-2 lg:items-center">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-[.22em] text-emerald-300">Learn your way</p>
            <h2 class="mt-3 max-w-xl text-3xl font-black leading-tight sm:text-5xl">{{ $homepageSettings['homepage_learning_modes_title'] ?? 'Build confident English skills, wherever you learn best.' }}</h2>
            <p class="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">{{ $homepageSettings['homepage_learning_modes_subtitle'] ?? 'Study online with the LMS or join a classroom programme at Alom’s English Center. Choose a path that fits your schedule.' }}</p>
            <div class="mt-7 inline-flex rounded-full border border-white/15 bg-white/5 p-1" role="tablist" aria-label="Choose a learning mode">
                <button type="button" id="learning-mode-online-tab" role="tab" aria-selected="true" aria-controls="learning-mode-online-panel" data-learning-mode-tab="online" class="rounded-full bg-emerald-400 px-5 py-2.5 text-sm font-extrabold text-slate-950">Online</button>
                <button type="button" id="learning-mode-classroom-tab" role="tab" aria-selected="false" aria-controls="learning-mode-classroom-panel" data-learning-mode-tab="classroom" class="rounded-full px-5 py-2.5 text-sm font-extrabold text-white transition hover:bg-white/10">Classroom</button>
            </div>
            <div class="mt-6">
                <div id="learning-mode-online-panel" role="tabpanel" aria-labelledby="learning-mode-online-tab" data-learning-mode-panel="online">
                    <p class="max-w-xl text-sm leading-6 text-slate-300">Learn through recorded lessons, course materials and scheduled live sessions. Revisit lessons whenever you need extra practice.</p>
                    <a href="{{ route('courses.index') }}" class="mt-5 inline-flex rounded-xl bg-emerald-400 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:bg-emerald-300">Explore online courses →</a>
                </div>
                <div id="learning-mode-classroom-panel" role="tabpanel" aria-labelledby="learning-mode-classroom-tab" data-learning-mode-panel="classroom" hidden>
                    <p class="max-w-xl text-sm leading-6 text-slate-300">Practise speaking and communication in a guided classroom programme with a clear learning routine.</p>
                    <a href="{{ route('coaching-admission.create') }}" class="mt-5 inline-flex rounded-xl bg-emerald-400 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:bg-emerald-300">Ask about admission →</a>
                </div>
            </div>
        </div>
        <div class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-gradient-to-br from-emerald-400/15 via-cyan-300/10 to-white/5 p-5 sm:p-8">
            <div class="pointer-events-none absolute -right-12 -top-16 h-56 w-56 rounded-full bg-emerald-400/20 blur-3xl"></div>
            <div class="relative grid gap-4 sm:grid-cols-2">
                <a href="{{ route('courses.index') }}" class="group rounded-3xl border border-white/10 p-5 transition hover:-translate-y-1 hover:border-emerald-300/50" style="background-color:#0d3a3e">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-300 text-2xl text-slate-950">⌨</span>
                    <h3 class="mt-5 text-lg font-extrabold">Online courses</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-300">Structured lessons and practice in your LMS account.</p>
                    <span class="mt-5 inline-block text-sm font-bold text-emerald-300">View courses →</span>
                </a>
                <a href="{{ route('coaching-admission.create') }}" class="group rounded-3xl border border-white/10 p-5 transition hover:-translate-y-1 hover:border-emerald-300/50 sm:mt-10" style="background-color:#0d3a3e">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-300 text-slate-950">@include('partials.icon',['name'=>'building','class'=>'h-6 w-6'])</span>
                    <h3 class="mt-5 text-lg font-extrabold">Classroom learning</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-300">Get information about guided in-person programmes.</p>
                    <span class="mt-5 inline-block text-sm font-bold text-emerald-200">Admission details →</span>
                </a>
                @if (isset($branches) && $branches->contains(fn ($branch) => filled($branch->address)))
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-5 sm:col-span-2">
                        <p class="text-xs font-extrabold uppercase tracking-[.18em] text-emerald-200">Our classroom locations</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($branches->filter(fn ($branch) => filled($branch->address))->take(6) as $branch)
                                <span class="rounded-full border border-white/15 bg-white/5 px-3 py-2 text-xs font-semibold text-white">{{ $branch->name }} <span class="text-slate-300">· {{ $branch->address }}</span></span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endif

@if (($homepageSettings['homepage_show_branches'] ?? '1') === '1')
<section data-home-section="branches" class="bg-white py-14 sm:py-18">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-extrabold uppercase tracking-[.2em] text-emerald-700">Learn in person</p><h2 class="mt-2 text-3xl font-black tracking-tight text-slate-900">{{ $homepageSettings['homepage_branches_title'] ?? 'Visit an English learning centre' }}</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ $homepageSettings['homepage_branches_subtitle'] ?? 'Find a nearby Alom’s English Center branch and ask about classroom programmes.' }}</p></div><a href="{{ route('coaching-admission.create') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-800 hover:border-emerald-300 hover:text-emerald-800">Classroom admission @include('partials.icon',['name'=>'arrow-right','class'=>'h-4 w-4'])</a></div>
        <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($branches as $branch)
                <a href="{{ route('contact') }}?branch={{ urlencode($branch->name) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg"><span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-emerald-700">@include('partials.icon',['name'=>'building'])</span><h3 class="mt-4 text-base font-extrabold text-slate-900">{{ $branch->name }}</h3><p class="mt-2 flex items-start gap-2 text-sm leading-6 text-slate-600"><span class="mt-1 text-emerald-700">@include('partials.icon',['name'=>'map-pin','class'=>'h-4 w-4'])</span>{{ $branch->address ?: 'Contact this centre for its address.' }}</p>@if($branch->phone)<p class="mt-3 text-sm font-bold text-emerald-700">{{ $branch->phone }}</p>@endif<span class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-emerald-700">Contact branch @include('partials.icon',['name'=>'arrow-right','class'=>'h-4 w-4'])</span></a>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 p-6 text-sm text-slate-600 sm:col-span-2 lg:col-span-4">Branch information will appear here when an active centre is added in Admin.</p>
            @endforelse
        </div>
    </div>
</section>
@endif

{{-- FEATURED --}}
@if (($homepageSettings['homepage_show_featured'] ?? '1') === '1')
<section data-home-section="featured" data-home-carousel="featured" data-home-carousel-layout="{{ $homepageSettings['homepage_featured_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}" class="py-16" style="background-color:{{ $homepageSettings['homepage_featured_bg'] ?? '#ffffff' }};--home-section-text:{{ $homepageSettings['homepage_featured_text'] ?? '#0f172a' }}">
    <div class="mx-auto max-w-7xl px-4">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_featured_title'] ?? 'Featured courses' }}</h2>
        <p class="mt-1 text-slate-500">{{ $homepageSettings['homepage_featured_subtitle'] ?? 'Hand-picked by our academic team.' }}</p>
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($featured as $course)
                @include('partials.course-card', ['course' => $course])
            @empty
                <div class="home-highlight-panel rounded-3xl border border-slate-200 bg-white p-6 sm:col-span-2 lg:col-span-3">
                    <h3 class="font-extrabold text-slate-900">Featured courses are being selected</h3>
                    <p class="mt-1 text-sm text-slate-600">See all currently available courses in the catalogue.</p>
                    <a href="{{ route('courses.index') }}" class="mt-4 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Browse courses →</a>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endif


{{-- Courses, live learning, exams and teacher content follow the slider as independent sections. --}}

{{-- LATEST --}}
@if (($homepageSettings['homepage_show_latest'] ?? '1') === '1')
<section data-home-section="latest" data-home-carousel="latest" data-home-carousel-layout="{{ $homepageSettings['homepage_latest_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}" class="py-16" style="background-color:{{ $homepageSettings['homepage_latest_bg'] ?? '#f8fafc' }};--home-section-text:{{ $homepageSettings['homepage_latest_text'] ?? '#0f172a' }}"><div class="mx-auto max-w-7xl px-4">
    <div class="flex items-end justify-between">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_latest_title'] ?? 'Latest courses' }}</h2>
        <div class="text-right"><p class="mt-1 text-sm text-slate-500">{{ $homepageSettings['homepage_latest_subtitle'] ?? 'Explore our newest learning opportunities.' }}</p><a href="{{ route('courses.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">Browse all →</a></div>
    </div>
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($latest as $course)
            @include('partials.course-card', ['course' => $course])
        @empty
            <div class="home-highlight-panel rounded-3xl border border-slate-200 bg-white p-6 sm:col-span-2 lg:col-span-4">
                <h3 class="font-extrabold text-slate-900">New courses are on the way</h3>
                <p class="mt-1 text-sm text-slate-600">Visit the course catalogue to see what is currently available.</p>
                <a href="{{ route('courses.index') }}" class="mt-4 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Open course catalogue →</a>
            </div>
        @endforelse
    </div>
    </div>
</section>
@endif

{{-- UPCOMING LIVE CLASSES --}}
@if (($homepageSettings['homepage_show_live_classes'] ?? '1') === '1')
<section data-home-section="live_classes" data-home-carousel="live_classes" data-home-carousel-layout="{{ $homepageSettings['homepage_live_classes_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}" class="py-16" style="background-color:{{ $homepageSettings['homepage_live_classes_bg'] ?? '#ffffff' }};--home-section-text:{{ $homepageSettings['homepage_live_classes_text'] ?? '#0f172a' }}">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_live_classes_title'] ?? 'Upcoming live classes' }}</h2><p class="mt-1 text-slate-500">{{ $homepageSettings['homepage_live_classes_subtitle'] ?? 'Join scheduled learning sessions with our instructors.' }}</p></div>
            <a href="{{ route('courses.index') }}" class="text-sm font-bold text-emerald-700">Explore courses →</a>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($upcomingLiveClasses as $class)
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-center justify-between gap-2"><span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">{{ $class->platform }}</span><span class="text-xs font-semibold text-slate-500">{{ $class->starts_at->format('d M Y') }}</span></div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900">{{ $class->title }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $class->course?->title }}</p>
                    <p class="mt-3 text-sm text-slate-600">{{ $class->starts_at->format('h:i A') }} · {{ $class->duration_minutes }} minutes</p>
                    <a href="{{ $class->course ? route('courses.show', $class->course->slug) : route('courses.index') }}" class="mt-4 inline-flex text-sm font-bold text-emerald-700">View course details →</a>
                </article>
            @empty
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2 lg:col-span-3">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700">@include('partials.icon',['name'=>'monitor','class'=>'h-6 w-6'])</span>
                    <h3 class="mt-4 font-extrabold text-slate-900">No live classes scheduled right now</h3>
                    <p class="mt-1 text-sm text-slate-600">Upcoming sessions will appear here when they are published.</p>
                    <a href="{{ route('courses.index') }}" class="mt-4 inline-flex text-sm font-bold text-emerald-700">Explore courses →</a>
                </article>
            @endforelse
        </div>
    </div>
</section>
@endif

{{-- UPCOMING EXAMS --}}
@if (($homepageSettings['homepage_show_exams'] ?? '1') === '1')
<section data-home-section="exams" data-home-carousel="exams" data-home-carousel-layout="{{ $homepageSettings['homepage_exams_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}" class="py-16" style="background-color:{{ $homepageSettings['homepage_exams_bg'] ?? '#f8fafc' }};--home-section-text:{{ $homepageSettings['homepage_exams_text'] ?? '#0f172a' }}"><div class="mx-auto max-w-7xl px-4">
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_exams_title'] ?? 'Exams & assessments' }}</h2><p class="mt-1 text-slate-500">{{ $homepageSettings['homepage_exams_subtitle'] ?? 'Prepare, practise and track your progress.' }}</p></div><a href="{{ route('login') }}" class="text-sm font-bold text-emerald-700">Student login →</a></div>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($upcomingExams as $exam)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $exam->course?->title }}</span>
                <h3 class="mt-2 font-bold text-slate-900">{{ $exam->title }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ $exam->total_marks }} marks · {{ $exam->duration_minutes }} minutes</p>
                <p class="mt-1 text-xs text-slate-500">{{ $exam->starts_at ? $exam->starts_at->format('d M Y, h:i A') : 'Schedule announced in student portal' }}</p>
            </article>
        @empty
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:col-span-2 lg:col-span-3">
                <h3 class="font-extrabold text-slate-900">Assessments are available inside your courses</h3>
                <p class="mt-1 text-sm text-slate-600">Published exams linked to an active course will be listed here when scheduled.</p>
                <a href="{{ route('courses.index') }}" class="mt-4 inline-flex text-sm font-bold text-emerald-700">Find a course →</a>
            </article>
        @endforelse
    </div>
    </div>
</section>
@endif

{{-- TEACHER PROFILES --}}
@if (($homepageSettings['homepage_show_teachers'] ?? '1') === '1')
<section data-home-section="teachers" data-home-carousel="teachers" data-home-carousel-layout="{{ $homepageSettings['homepage_teachers_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}" id="teachers" class="py-16" style="background-color:{{ $homepageSettings['homepage_teachers_bg'] ?? '#f8fafc' }};--home-section-text:{{ $homepageSettings['homepage_teachers_text'] ?? '#0f172a' }}">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex flex-wrap items-end justify-between gap-4"><div class="text-center sm:text-left"><h2 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_teachers_title'] ?? 'Meet our teachers' }}</h2><p class="mt-1 text-slate-500">{{ $homepageSettings['homepage_teachers_subtitle'] ?? 'Learn with experienced instructors and practical guidance.' }}</p></div><a href="{{ route('instructors.index') }}" class="rounded-full border border-emerald-200 bg-white px-5 py-2.5 text-sm font-bold text-emerald-800 shadow-sm transition hover:bg-emerald-50">View all instructors →</a></div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($teachers as $teacher)
                <article class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                    <img src="{{ $teacher->avatarUrl() }}" alt="{{ $teacher->name }}" class="mx-auto h-20 w-20 rounded-full object-cover ring-4 ring-emerald-50">
                    <h3 class="mt-4 font-bold text-slate-900"><a href="{{ route('instructors.show', $teacher) }}" class="hover:text-emerald-700">{{ $teacher->name }}</a></h3>
                    @if ($teacher->designation)<p class="mt-1 text-sm font-semibold text-emerald-700">{{ $teacher->designation }}</p>@endif
                    @if ($teacher->bio)<p class="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-600">{{ $teacher->bio }}</p>@endif
                    <p class="mt-3 text-xs text-slate-500">{{ $teacher->courses_count }} published learning courses</p>
                </article>
            @empty
                <article class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:col-span-2 lg:col-span-3">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-sky-100 text-sky-700">@include('partials.icon',['name'=>'graduation-cap','class'=>'h-6 w-6'])</span>
                    <h3 class="mt-4 font-extrabold text-slate-900">Instructor profiles are being updated</h3>
                    <p class="mt-1 text-sm text-slate-600">Teacher profiles will appear here when published.</p>
                    <a href="{{ route('courses.index') }}" class="mt-4 inline-flex text-sm font-bold text-emerald-700">Explore courses →</a>
                </article>
            @endforelse
        </div>
    </div>
</section>
@endif

{{-- VERIFIED BOARD RESULTS / ACHIEVEMENTS --}}
@if (($homepageSettings['homepage_show_board_results'] ?? '0') === '1' && !empty($homepageSettings['homepage_board_results_body']))
<section data-home-section="board_results" id="board-results" class="py-16" style="background-color:{{ $homepageSettings['homepage_board_results_bg'] ?? '#064e3b' }};--home-section-text:{{ $homepageSettings['homepage_board_results_text'] ?? '#ffffff' }}">
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
<section data-home-section="reviews" data-home-carousel="reviews" data-home-carousel-layout="{{ $homepageSettings['homepage_reviews_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}" class="student-testimonials py-16 text-white" style="background:{{ $homepageSettings['homepage_testimonial_bg'] ?? '#111827' }};--home-section-text:{{ $homepageSettings['homepage_testimonial_text'] ?? '#ffffff' }}">
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
                    @if($review->videoEmbedUrl())<button type="button" data-review-video="{{ $review->videoEmbedUrl() }}" data-review-name="{{ $review->user?->name ?? 'Student' }}" class="group relative mb-4 block w-full overflow-hidden rounded-xl bg-slate-900 text-left"><img src="{{ $review->user?->avatarUrl() ?? asset('images/aec-logo.svg') }}" alt="" class="aspect-video w-full object-cover opacity-45 transition group-hover:opacity-60"><span class="absolute inset-0 grid place-items-center"><span class="grid h-14 w-14 place-items-center rounded-full bg-emerald-500 text-white shadow-lg">@include('partials.icon',['name'=>'play','class'=>'h-6 w-6'])</span></span><span class="absolute bottom-3 left-3 text-xs font-bold text-white">Play video review</span></button>@endif
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
<dialog id="review-video-modal" class="w-[min(92vw,56rem)] max-w-none overflow-hidden rounded-2xl border-0 bg-slate-950 p-0 text-white shadow-2xl backdrop:bg-slate-950/80"><div class="flex items-center justify-between px-4 py-3"><h2 id="review-video-title" class="font-bold">Student video review</h2><button type="button" data-review-video-close class="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-xl" aria-label="Close video">×</button></div><iframe id="review-video-frame" class="aspect-video w-full" src="" title="Student video review" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></dialog>
@push('scripts')
<style>
.student-testimonials{background:linear-gradient(135deg,#111827 0%,#172554 55%,#0f172a 100%)}
</style>
<script>(()=>{const modal=document.getElementById('review-video-modal'),frame=document.getElementById('review-video-frame'),title=document.getElementById('review-video-title');if(!modal||!frame)return;document.querySelectorAll('[data-review-video]').forEach(button=>button.addEventListener('click',()=>{const url=button.dataset.reviewVideo;if(!/^https:\/\/(www\.)?(youtube-nocookie\.com|player\.vimeo\.com)\//.test(url))return;frame.src=url+(url.includes('?')?'&':'?')+'autoplay=1';title.textContent=button.dataset.reviewName+' · Video review';modal.showModal()}));const close=()=>{modal.close();frame.src=''};modal.querySelector('[data-review-video-close]').addEventListener('click',close);modal.addEventListener('click',event=>{if(event.target===modal)close()});modal.addEventListener('close',()=>{frame.src=''})})();</script>
@endpush
@endif

{{-- EBOOKS --}}
@if (($homepageSettings['homepage_show_ebooks'] ?? '1') === '1')
<section data-home-section="ebooks" data-home-carousel="ebooks" data-home-carousel-layout="{{ $homepageSettings['homepage_ebooks_layout'] ?? 'carousel' }}" data-home-carousel-autoplay="{{ ($homepageSettings['homepage_carousel_autoplay'] ?? '0') === '1' ? '1' : '0' }}" data-home-carousel-speed="{{ (int) ($homepageSettings['homepage_carousel_speed'] ?? 6) }}" class="py-16" style="background-color:{{ $homepageSettings['homepage_ebooks_bg'] ?? '#ffffff' }};--home-section-text:{{ $homepageSettings['homepage_ebooks_text'] ?? '#0f172a' }}"><div class="mx-auto max-w-7xl px-4">
    <div class="flex items-end justify-between">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ $homepageSettings['homepage_ebooks_title'] ?? 'E-books & notes' }}</h2><p class="mt-1 text-sm text-slate-500">{{ $homepageSettings['homepage_ebooks_subtitle'] ?? 'Study guides and resources for learning anywhere.' }}</p>
        <a href="{{ route('ebooks.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">All e-books →</a>
    </div>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($ebooks as $ebook)
            <a href="{{ route('ebooks.show', $ebook->slug) }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm hover:shadow-md">
                <img src="{{ $ebook->coverUrl() }}" class="h-48 w-full object-cover" alt="">
                <div class="p-4">
                    <h3 class="line-clamp-2 font-bold text-slate-900 group-hover:text-emerald-700">{{ $ebook->title }}</h3>
                    <p class="mt-1 text-sm font-semibold text-emerald-700">{{ $ebook->is_free ? 'Free' : '৳'.number_format($ebook->price) }}</p>
                </div>
            </a>
        @empty
            <div class="home-highlight-panel rounded-3xl border border-slate-200 bg-white p-6 sm:col-span-2 lg:col-span-4">
                <h3 class="font-extrabold text-slate-900">Study resources will appear here</h3>
                <p class="mt-1 text-sm text-slate-600">Browse the learning library for available notes and e-books.</p>
                <a href="{{ route('ebooks.index') }}" class="mt-4 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Open study library →</a>
            </div>
        @endforelse
    </div>
    </div>
</section>
@endif

{{-- STUDENT LEARNING HUB --}}
@if (($homepageSettings['homepage_show_learning_hub'] ?? '1') === '1')
<section data-home-section="learning_hub" class="overflow-hidden py-14 text-white sm:py-20" style="background-color:#071f2c">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 lg:grid-cols-2 lg:items-center">
        <div>
            <span class="inline-flex rounded-full border border-cyan-200/20 bg-cyan-200/10 px-3 py-1.5 text-xs font-extrabold uppercase tracking-wider text-cyan-200">Your learning space</span>
            <h2 class="mt-4 max-w-2xl text-3xl font-black leading-tight sm:text-5xl">Keep your courses, lessons and progress together.</h2>
            <p class="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">Sign in to continue an enrolled course, revisit learning materials and see your course progress in one place.</p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('login') }}" class="rounded-xl bg-emerald-400 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:bg-emerald-300">Sign in to your account →</a>
                <a href="{{ route('courses.index') }}" class="rounded-xl border border-white/20 bg-white/5 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/10">Find a course</a>
            </div>
        </div>
        <div class="relative rounded-[2rem] border border-white/10 bg-gradient-to-br from-cyan-300/15 via-emerald-300/10 to-white/5 p-5 sm:p-7">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-white/10 p-5" style="background-color:#102f3b"><span class="text-emerald-300">@include('partials.icon',['name'=>'book-open','class'=>'h-6 w-6'])</span><h3 class="mt-3 font-extrabold">My courses</h3><p class="mt-1 text-sm leading-6 text-slate-300">Open the lessons included with your enrolment.</p></div>
                <div class="rounded-2xl border border-white/10 p-5" style="background-color:#102f3b"><span class="text-cyan-200">@include('partials.icon',['name'=>'clock','class'=>'h-6 w-6'])</span><h3 class="mt-3 font-extrabold">Learning progress</h3><p class="mt-1 text-sm leading-6 text-slate-300">Pick up where you left off in an active course.</p></div>
                <div class="rounded-2xl border border-white/10 p-5 sm:col-span-2" style="background-color:#102f3b"><p class="text-xs font-extrabold uppercase tracking-wider text-emerald-200">Alom’s English Center</p><p class="mt-2 text-sm leading-6 text-slate-300">Course access and progress appear after you enrol and sign in with your learner account.</p></div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ONLINE / CLASSROOM ADMISSION --}}
@if (($homepageSettings['homepage_show_admission'] ?? '1') === '1')
<section data-home-section="admission" class="py-8" style="background-color:{{ $homepageSettings['homepage_admission_bg'] ?? '#0f172a' }};--home-section-text:{{ $homepageSettings['homepage_admission_text'] ?? '#ffffff' }}">
    <div class="relative mx-auto max-w-7xl overflow-hidden rounded-3xl px-8 py-14 text-center text-white shadow-2xl sm:px-12" style="background:linear-gradient(135deg,#020617 0%,#0b383a 55%,#064e3b 100%)">
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
#homepage-sections[data-home-theme="light"] .learning-path-section :is(h2,p){color:var(--home-section-text)}
#homepage-sections[data-home-theme="light"] .home-stats-section :is(h2,strong,p){color:var(--home-section-text)}
#homepage-sections[data-home-theme="light"] .student-testimonials{color:var(--home-section-text)}
#homepage-sections[data-home-theme="light"] .student-testimonials :is(.text-slate-300,.text-slate-200,.text-white){color:var(--home-section-text)}
#homepage-sections[data-home-theme="dark"]{background:#08111f;color:#e5edf8}
#homepage-sections [data-home-section]:not([data-home-section="hero"]){color:var(--home-section-text)}
#homepage-sections[data-home-theme="dark"] [data-home-section]:not([data-home-section="hero"]){color:var(--home-section-text,#e5edf8)}
#homepage-sections[data-home-theme="dark"] [data-home-section]:not([data-home-section="hero"]) :is(.border-slate-200,.border-gray-200){border-color:#2b3b52}
.aec-reference-home .home-category-card{isolation:isolate;box-shadow:0 14px 34px rgba(15,23,42,.08)}
.aec-reference-home .home-category-card:before{position:absolute;z-index:-1;right:-1.8rem;bottom:-3.5rem;width:9rem;height:9rem;border-radius:9999px;background:rgba(255,255,255,.42);content:"";transition:transform .25s ease}
.aec-reference-home .home-category-card:hover:before{transform:scale(1.16)}
.aec-reference-home .home-highlight-panel{border-radius:1.75rem;background:linear-gradient(135deg,#fff 0%,#f2f8ff 55%,#f2fbf8 100%);box-shadow:0 18px 42px rgba(15,23,42,.08)}
.aec-reference-home [data-home-section="featured"] article,.aec-reference-home [data-home-section="latest"] article{border-radius:1.25rem;box-shadow:0 14px 34px rgba(15,23,42,.08)}
.aec-reference-home [data-home-section="stats"]>div>div{border-radius:1.5rem}
@media(max-width:639px){.aec-reference-home .home-category-card{min-height:160px}.aec-reference-home [data-home-section]:not([data-home-section="hero"]){scroll-margin-top:1rem}}
#homepage-sections[data-home-animation]:not([data-home-animation="none"]) [data-home-reveal]{opacity:0;transition:opacity var(--aec-reveal-duration,650ms) ease,transform var(--aec-reveal-duration,650ms) cubic-bezier(.2,.7,.2,1);will-change:opacity,transform}
#homepage-sections[data-home-animation="rise"] [data-home-reveal]{transform:translateY(28px)}
#homepage-sections[data-home-animation="slide"] [data-home-reveal]{transform:translateX(32px)}
#homepage-sections[data-home-animation="zoom"] [data-home-reveal]{transform:scale(.96)}
#homepage-sections[data-home-animation="fade"] [data-home-reveal]{transform:none}
#homepage-sections[data-home-animation]:not([data-home-animation="none"]) [data-home-reveal].is-visible{opacity:1;transform:none}
@media(prefers-reduced-motion:reduce){#homepage-sections [data-home-reveal]{opacity:1!important;transform:none!important;transition:none!important}}
</style>
<script>
(() => {
 const root=document.getElementById('homepage-sections');if(!root)return;
 const style=root.dataset.homeAnimation||'rise';
 const duration=Math.min(1200,Math.max(200,Number(root.dataset.homeAnimationSpeed)||650));
 root.style.setProperty('--aec-reveal-duration',duration+'ms');
 const sections=[...root.querySelectorAll(':scope > [data-home-section]:not([data-home-section="hero"])')];
 if(style==='none'||typeof window.IntersectionObserver!=='function'||window.matchMedia('(prefers-reduced-motion: reduce)').matches){sections.forEach(s=>s.classList.add('is-visible'));return;}
 const visibleAtLoad=new Set();
 sections.forEach(s=>{
  s.setAttribute('data-home-reveal','');
  const bounds=s.getBoundingClientRect();
  if(bounds.bottom>0&&bounds.top<window.innerHeight-35){s.classList.add('is-visible');visibleAtLoad.add(s);}
 });
 const observer=new window.IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}}),{threshold:.08,rootMargin:'0px 0px -35px 0px'});
 sections.forEach(s=>{if(!visibleAtLoad.has(s))observer.observe(s);});
})();
</script>
@endpush

@push('scripts')
<script>
(() => {
    const container = document.getElementById('homepage-sections');
    if (!container) return;
    const sections = new Map([...container.querySelectorAll(':scope > [data-home-section]')].map(section => [section.dataset.homeSection, section]));
    const savedOrder = @json($homepageSectionOrder);
    // The hero banner stays first; its artwork is managed from Slider Settings.
    const fixedHero = sections.get('hero');
    if (fixedHero) { container.appendChild(fixedHero); sections.delete('hero'); }
    [...savedOrder.filter(key => key !== 'hero'), ...sections.keys()].forEach(key => {
        const section = sections.get(key);
        if (section) { container.appendChild(section); sections.delete(key); }
    });
    for (const section of sections.values()) container.appendChild(section);
})();
</script>
@endpush

@push('scripts')
<script>
(() => {
 const section=document.querySelector('[data-home-section="learning_modes"]');if(!section)return;
 const tabs=[...section.querySelectorAll('[data-learning-mode-tab]')],panels=[...section.querySelectorAll('[data-learning-mode-panel]')];
 const activate=mode=>{
  tabs.forEach(tab=>{const active=tab.dataset.learningModeTab===mode;tab.setAttribute('aria-selected',active?'true':'false');tab.tabIndex=active?0:-1;tab.classList.toggle('bg-emerald-400',active);tab.classList.toggle('text-slate-950',active);tab.classList.toggle('text-white',!active);});
  panels.forEach(panel=>{panel.hidden=panel.dataset.learningModePanel!==mode;});
 };
 tabs.forEach((tab,index)=>{
  tab.addEventListener('click',()=>activate(tab.dataset.learningModeTab));
  tab.addEventListener('keydown',event=>{
   if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
   event.preventDefault();
   const next=event.key==='Home'?0:event.key==='End'?tabs.length-1:(index+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;
   tabs[next].focus();tabs[next].click();
  });
 });
 activate('online');
})();
</script>
@endpush

@push('scripts')
<style>
.home-carousel-track{scrollbar-width:none;scroll-behavior:smooth;scroll-snap-type:x mandatory}
.home-carousel-track::-webkit-scrollbar{display:none}
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
  const track=section.querySelector('.mt-8.grid, .mt-9.grid');if(!track||track.children.length<2)return;
  track.classList.remove('grid','sm:grid-cols-2','md:grid-cols-3','lg:grid-cols-3','lg:grid-cols-4','gap-4','gap-5','gap-6');
  track.classList.add('home-carousel-track','flex','gap-5','overflow-x-auto','pb-3');
  const controls=document.createElement('div');controls.className='home-carousel-controls ml-auto flex shrink-0 gap-2';
  controls.innerHTML='<button type="button" aria-label="Previous items" class="grid h-10 w-10 place-items-center rounded-full border border-slate-300 bg-white text-xl text-slate-700 shadow-sm">‹</button><button type="button" aria-label="Next items" class="grid h-10 w-10 place-items-center rounded-full border border-slate-300 bg-white text-xl text-slate-700 shadow-sm">›</button>';
  const heading=section.querySelector('.mx-auto.max-w-7xl>div.flex.items-end.justify-between, .mx-auto.max-w-7xl>div.flex.flex-wrap.items-end.justify-between');
  if(heading){heading.classList.add('gap-4');heading.appendChild(controls);}else{
   const content=section.querySelector('.mx-auto.max-w-7xl');
   controls.classList.remove('ml-auto');controls.classList.add('mx-auto','mt-4','w-fit','justify-center');
   if(content)content.insertBefore(controls,track);
  }
  const [prev,next]=controls.querySelectorAll('button');
  const step=()=>Math.max(track.clientWidth*.82,220);
  const move=direction=>track.scrollBy({left:direction*step(),behavior:reduced.matches?'auto':'smooth'});
  prev.addEventListener('click',()=>move(-1));next.addEventListener('click',()=>move(1));
  const update=()=>{prev.disabled=track.scrollLeft<=2;next.disabled=track.scrollLeft+track.clientWidth>=track.scrollWidth-2;};
  track.addEventListener('scroll',update,{passive:true});window.addEventListener('resize',update);update();
  track.setAttribute('aria-label',section.dataset.homeCarousel.replace('_',' ')+' carousel');track.setAttribute('tabindex','0');track.setAttribute('role','region');
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
