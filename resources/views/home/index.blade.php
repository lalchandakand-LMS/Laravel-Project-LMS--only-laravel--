@extends('layouts.app')
@section('title', 'English Courses, Spoken English & Grammar')
@section('meta_description', 'Learn spoken English, grammar, vocabulary, writing and communication skills with guided courses at Alom\'s English Center.')
@section('content')

{{-- HERO SLIDER --}}
@php
    $slides = $sliders ?? collect();
@endphp
@if ($slides->count())
<section class="relative overflow-hidden bg-slate-900 text-white">
    <div id="heroSlides" class="hero-carousel relative" tabindex="0" aria-roledescription="carousel" aria-label="Featured learning highlights">
        @foreach ($slides as $i => $slide)
            @php
                [$theme, $badge, $accent, $button] = $slide->palette();
            @endphp
            <div class="hero-slide {{ $i === 0 ? 'is-active' : 'hidden' }} bg-gradient-to-br {{ $theme }}" data-slide="{{ $i }}" data-hero-slide aria-hidden="{{ $i === 0 ? 'false' : 'true' }}">
                <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-emerald-500/20 blur-3xl"></div>
                <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 lg:grid-cols-2 lg:py-20">
                    <div>
                        @if ($slide->badge)
                            <span class="inline-flex items-center gap-2 rounded-full border {{ $badge }} px-3 py-1 text-xs font-semibold">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> {{ $slide->badge }}
                            </span>
                        @endif
                        <h1 class="mt-5 text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl">{{ $slide->title }}</h1>
                        @if ($slide->subtitle)
                            <p class="mt-4 max-w-xl text-lg text-slate-300">{{ $slide->subtitle }}</p>
                        @endif
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ $slide->button_url ?: route('courses.index') }}" class="rounded-xl px-6 py-3 font-bold shadow-lg transition hover:-translate-y-0.5 {{ $button }}">{{ $slide->button_text ?: 'Explore courses' }}</a>
                            <a href="{{ auth()->check() ? auth()->user()->dashboardRoute() : route('register') }}" class="rounded-xl border px-6 py-3 font-semibold border-white/20 text-white hover:bg-white/10">Create free account</a>
                        </div>
                        <dl class="mt-10 grid max-w-md grid-cols-3 gap-4">
                            @foreach ([['Students', $stats['students']], ['Courses', $stats['courses']], ['Teachers', $stats['instructors']]] as [$label, $value])
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                    <dd class="text-2xl font-extrabold text-white">{{ number_format($value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                    <div class="relative">
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-3 shadow-2xl backdrop-blur">
                            <img src="{{ $slide->imageUrl() ?: asset('images/aec-logo.svg') }}"
                                 alt="{{ $slide->title }}" class="h-72 w-full rounded-2xl object-cover sm:h-96">
                        </div>
                        <div class="absolute -bottom-6 -left-4 rounded-2xl border border-white/10 bg-slate-900/90 px-4 py-3 shadow-xl backdrop-blur">
                            <p class="text-2xl font-extrabold text-emerald-400">{{ number_format($stats['lessons']) }}</p>
                            <p class="text-xs text-slate-300">learning activities</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        @if ($slides->count() > 1)
            <button type="button" onclick="heroGo(-1)" aria-label="Previous slide"
                class="hero-arrow absolute left-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 p-3 text-slate-900 shadow-lg hover:bg-white">‹</button>
            <button type="button" onclick="heroGo(1)" aria-label="Next slide"
                class="hero-arrow absolute right-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 p-3 text-slate-900 shadow-lg hover:bg-white">›</button>
            <div class="absolute bottom-4 left-1/2 z-10 flex -translate-x-1/2 gap-2">
                @foreach ($slides as $i => $slide)
                    <button type="button" onclick="heroShow({{ $i }})" aria-label="Slide {{ $i + 1 }}"
                        data-hero-dot class="hero-dot h-2.5 w-2.5 rounded-full transition" aria-current="{{ $i === 0 ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
        @endif
    </div>
</section>

@push('scripts')
<script>
(() => {
 const slides=[...document.querySelectorAll('[data-hero-slide]')],dots=[...document.querySelectorAll('[data-hero-dot]')],root=document.getElementById('heroSlides');
 if(!root||!slides.length)return;
 let active=Math.max(0,slides.findIndex(s=>!s.classList.contains('hidden'))),timer=null,startX=0,startY=0;
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
 const stop=()=>{if(timer)clearInterval(timer);timer=null;};
 function restart(){stop();if(slides.length>1&&!reduced.matches&&!root.matches(':hover')&&!root.contains(document.activeElement))timer=setInterval(()=>show(active+1),6500);}
 function show(index){
  const next=(index+slides.length)%slides.length;if(next===active&&!slides[next].classList.contains('hidden')){restart();return;}
  const old=slides[active],incoming=slides[next];old.classList.remove('is-active');old.setAttribute('aria-hidden','true');
  setTimeout(()=>{if(old!==slides[active])old.classList.add('hidden');},reduced.matches?0:450);incoming.classList.remove('hidden');incoming.setAttribute('aria-hidden','false');
  requestAnimationFrame(()=>requestAnimationFrame(()=>incoming.classList.add('is-active')));active=next;
  dots.forEach((dot,i)=>{dot.classList.toggle('is-active',i===active);dot.setAttribute('aria-current',i===active?'true':'false');});restart();
 }
 window.heroShow=show;window.heroGo=dir=>show(active+dir);
 root.addEventListener('mouseenter',stop);root.addEventListener('mouseleave',restart);root.addEventListener('focusin',stop);root.addEventListener('focusout',e=>{if(!root.contains(e.relatedTarget))restart();});
 root.addEventListener('keydown',e=>{if(e.key==='ArrowRight'){e.preventDefault();show(active+1);}if(e.key==='ArrowLeft'){e.preventDefault();show(active-1);}});
 root.addEventListener('touchstart',e=>{startX=e.changedTouches[0].clientX;startY=e.changedTouches[0].clientY;stop();},{passive:true});
 root.addEventListener('touchend',e=>{const dx=e.changedTouches[0].clientX-startX,dy=e.changedTouches[0].clientY-startY;if(Math.abs(dx)>45&&Math.abs(dx)>Math.abs(dy))show(active+(dx<0?1:-1));else restart();},{passive:true});
 slides.forEach((s,i)=>{s.setAttribute('aria-hidden',i===active?'false':'true');if(i===active)s.classList.add('is-active');});
 dots.forEach((d,i)=>{d.setAttribute('aria-current',i===active?'true':'false');d.classList.toggle('is-active',i===active);});reduced.addEventListener('change',restart);restart();
})();
</script>
@endpush
@else
{{-- HERO --}}
<section class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950 text-white">
    <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-emerald-500/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 left-1/3 h-96 w-96 rounded-full bg-teal-400/10 blur-3xl"></div>
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-20 lg:grid-cols-2">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span> Build practical English skills at your own pace
            </span>
            <h1 class="mt-5 text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl">
                Learn English with confidence at <span class="text-emerald-400">Alom's English Center</span>
            </h1>
            <p class="mt-4 max-w-xl text-lg text-slate-300">
                Improve your English with structured lessons, expert guidance, speaking practice and progress tracking.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('courses.index') }}" class="rounded-xl bg-emerald-500 px-6 py-3 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 hover:bg-emerald-400">Explore courses</a>
                <a href="{{ route('register') }}" class="rounded-xl border border-white/20 px-6 py-3 font-semibold text-white hover:bg-white/10">Create free account</a>
            </div>
            <dl class="mt-10 grid max-w-md grid-cols-3 gap-4">
                @foreach ([['Students', $stats['students']], ['Courses', $stats['courses']], ['Teachers', $stats['instructors']]] as [$label, $value])
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                        <dd class="text-2xl font-extrabold text-white">{{ number_format($value) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        <div class="relative">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-3 shadow-2xl backdrop-blur">
                <div class="grid h-72 place-items-center rounded-2xl bg-white/5 text-center sm:h-96"><div><span class="text-7xl">📚</span><p class="mt-4 text-2xl font-bold">Learn. Practise. Progress.</p><p class="mt-2 text-sm text-slate-300">Alom’s English Center</p></div></div>
            </div>
            <div class="absolute -bottom-6 -left-4 rounded-2xl border border-white/10 bg-slate-900/90 px-4 py-3 shadow-xl backdrop-blur">
                <p class="text-2xl font-extrabold text-emerald-400">{{ number_format($stats['lessons']) }}</p>
                <p class="text-xs text-slate-300">video lessons</p>
            </div>
        </div>
    </div>
</section>

@endif

{{-- CATEGORIES --}}
<section data-home-carousel="categories" data-home-carousel-layout="{{ $homepageSettings['homepage_learning_layout'] ?? 'carousel' }}"  class="mx-auto max-w-7xl px-4 py-16" style="background-color: {{ $homepageSettings['homepage_learning_bg'] ?? '#ffffff' }}; color: {{ $homepageSettings['homepage_learning_text'] ?? '#0f172a' }}">
    <div class="flex items-end justify-between">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $homepageSettings['homepage_learning_title'] ?? 'Choose your learning path' }}</h2>
            <p class="mt-1">{{ $homepageSettings['homepage_learning_subtitle'] ?? 'Choose a course to improve speaking, grammar, vocabulary, writing or IELTS skills.' }}</p>
        </div>
        <a href="{{ route('courses.index') }}" class="hidden text-sm font-semibold text-emerald-700 hover:underline sm:block">See all courses →</a>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($categories as $category)
            <a href="{{ route('categories.show', $category->slug) }}"
               class="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-emerald-50 text-2xl">{{ $category->icon ?: '📘' }}</span>
                <div>
                    <h3 class="font-bold text-slate-900 group-hover:text-emerald-700">{{ $category->name }}</h3>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $category->description }}</p>
                    <span class="mt-2 inline-block text-xs font-semibold text-emerald-700">{{ $category->published_courses_count ?? $category->publishedCourses()->count() }} courses →</span>
                </div>
            </a>
        @endforeach
    </div>
</section>

{{-- FEATURED --}}
@if ($featured->count())
<section data-home-carousel="featured" data-home-carousel-layout="{{ $homepageSettings['homepage_featured_layout'] ?? 'carousel' }}"  class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-4">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Featured courses</h2>
        <p class="mt-1 text-slate-500">Hand-picked by our academic team.</p>
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($featured as $course)
                @include('partials.course-card', ['course' => $course])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- STATS BANNER --}}
<section style="background-color: {{ $homepageSettings['homepage_stats_bg'] ?? '#047857' }}; color: {{ $homepageSettings['homepage_stats_text'] ?? '#ffffff' }}">
    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-10 text-center text-white sm:grid-cols-4">
        @foreach ([['Students', $stats['students']], ['Courses', $stats['courses']], ['Teachers', $stats['instructors']], ['Lessons', $stats['lessons']]] as [$label, $value])
            <div>
                <p class="text-3xl font-extrabold">{{ number_format($value) }}+</p>
                <p class="text-sm text-emerald-100">{{ $label }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- LATEST --}}
<section data-home-carousel="latest" data-home-carousel-layout="{{ $homepageSettings['homepage_latest_layout'] ?? 'carousel' }}"  class="mx-auto max-w-7xl px-4 py-16">
    <div class="flex items-end justify-between">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Latest courses</h2>
        <a href="{{ route('courses.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">Browse all →</a>
    </div>
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($latest as $course)
            @include('partials.course-card', ['course' => $course])
        @endforeach
    </div>
</section>

{{-- UPCOMING LIVE CLASSES --}}
@if (($homepageSettings['homepage_show_live_classes'] ?? '1') === '1' && $upcomingLiveClasses->isNotEmpty())
<section data-home-carousel="live_classes" data-home-carousel-layout="{{ $homepageSettings['homepage_live_classes_layout'] ?? 'carousel' }}"  class="bg-white py-16">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">Upcoming live classes</h2><p class="mt-1 text-slate-500">Join scheduled learning sessions with our instructors.</p></div>
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
@if (($homepageSettings['homepage_show_exams'] ?? '1') === '1' && $upcomingExams->isNotEmpty())
<section data-home-carousel="exams" data-home-carousel-layout="{{ $homepageSettings['homepage_exams_layout'] ?? 'carousel' }}"  class="mx-auto max-w-7xl px-4 py-16">
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">Exams & assessments</h2><p class="mt-1 text-slate-500">Prepare, practise and track your progress.</p></div><a href="{{ route('login') }}" class="text-sm font-bold text-emerald-700">Student login →</a></div>
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
@if (($homepageSettings['homepage_show_teachers'] ?? '1') === '1' && $teachers->isNotEmpty())
<section data-home-carousel="teachers" data-home-carousel-layout="{{ $homepageSettings['homepage_teachers_layout'] ?? 'carousel' }}"  id="teachers" class="bg-slate-50 py-16">
    <div class="mx-auto max-w-7xl px-4">
        <div class="text-center"><h2 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">Meet our teachers</h2><p class="mt-1 text-slate-500">Learn with experienced instructors and practical guidance.</p></div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($teachers as $teacher)
                <article class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                    <img src="{{ $teacher->avatarUrl() }}" alt="{{ $teacher->name }}" class="mx-auto h-20 w-20 rounded-full object-cover ring-4 ring-emerald-50">
                    <h3 class="mt-4 font-bold text-slate-900">{{ $teacher->name }}</h3>
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
@if (($homepageSettings['homepage_show_board_results'] ?? '1') === '1' && !empty($homepageSettings['homepage_board_results_body']))
<section id="board-results" class="bg-emerald-950 py-16 text-white">
    <div class="mx-auto max-w-5xl px-4 text-center">
        <span class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Student achievements {{ $homepageSettings['homepage_board_results_year'] ?? '' }}</span>
        <h2 class="mt-3 text-3xl font-extrabold">{{ $homepageSettings['homepage_board_results_title'] ?? 'Our students’ achievements' }}</h2>
        @if (!empty($homepageSettings['homepage_board_results_subtitle']))<p class="mt-2 text-emerald-100">{{ $homepageSettings['homepage_board_results_subtitle'] }}</p>@endif
        <div class="mx-auto mt-6 max-w-3xl whitespace-pre-line text-base leading-8 text-emerald-50">{{ $homepageSettings['homepage_board_results_body'] }}</div>
    </div>
</section>
@endif

{{-- REVIEWS --}}
@if ($reviews->count())
<section data-home-carousel="reviews" data-home-carousel-layout="{{ $homepageSettings['homepage_reviews_layout'] ?? 'carousel' }}"  class="py-16" style="background-color: {{ $homepageSettings['homepage_testimonial_bg'] ?? '#f1f5f9' }}; color: {{ $homepageSettings['homepage_testimonial_text'] ?? '#0f172a' }}">
    <div class="mx-auto max-w-7xl px-4">
        <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $homepageSettings['homepage_testimonial_title'] ?? 'What our students say' }}</h2>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach ($reviews as $review)
                <figure class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-amber-700">{{ str_repeat('★', $review->rating) }}<span class="text-slate-500">{{ str_repeat('★', 5 - $review->rating) }}</span></div>
                    <blockquote class="mt-3 line-clamp-4 text-sm leading-relaxed text-slate-600">“{{ $review->comment }}”</blockquote>
                    <figcaption class="mt-4 flex items-center gap-3">
                        <img src="{{ $review->user->avatarUrl() }}" class="h-9 w-9 rounded-full" alt="">
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ $review->user->name }}</p>
                            <p class="text-xs text-slate-500">{{ Str::limit($review->course->title, 32) }}</p>
                        </div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- EBOOKS --}}
@if ($ebooks->count())
<section data-home-carousel="ebooks" data-home-carousel-layout="{{ $homepageSettings['homepage_ebooks_layout'] ?? 'carousel' }}"  class="mx-auto max-w-7xl px-4 py-16">
    <div class="flex items-end justify-between">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">E-books & notes</h2>
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
@if (($homepageSettings['homepage_show_admission'] ?? '1') === '1')
<section class="mx-auto max-w-7xl px-4 pb-8 pt-4">
    <div class="relative overflow-hidden rounded-3xl bg-slate-900 px-8 py-14 text-center text-white sm:px-12">
        <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-emerald-500/20 blur-3xl"></div>
        <span class="relative inline-flex rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-bold text-emerald-300">Admissions & enrolment</span>
        <h2 class="relative mt-4 text-3xl font-extrabold">{{ $homepageSettings['homepage_admission_title'] ?? 'Join an online or classroom batch' }}</h2>
        <p class="relative mx-auto mt-3 max-w-2xl text-slate-300">{{ $homepageSettings['homepage_admission_subtitle'] ?? 'Choose a course and start learning with Alom’s English Center.' }}</p>
        <a href="{{ $homepageSettings['homepage_admission_button_url'] ?? '/courses' }}" class="relative mt-7 inline-block rounded-xl bg-emerald-500 px-7 py-3 font-bold text-slate-950 hover:bg-emerald-400">{{ $homepageSettings['homepage_admission_button_text'] ?? 'Apply for admission' }}</a>
    </div>
</section>
@endif
@endsection
