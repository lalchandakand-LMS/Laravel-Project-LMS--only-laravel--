@extends('layouts.app')
@section('title', 'About Alom’s English Center')
@section('meta_description', 'Meet Alom’s English Center: practical English lessons, speaking practice, grammar, vocabulary and teacher-guided learning.')
@section('content')
<div class="aec-public-page mx-auto max-w-7xl">
    @include('partials.public-page-hero', [
        'eyebrow' => 'About our learning community',
        'title' => "English learning built around you",
        'description' => filled(setting('about_text')) ? setting('about_text') : "Alom's English Center helps learners build practical English skills through structured lessons, guided practice and supportive teaching. Our goal is to make quality English education accessible to learners at every level.",
        'actionLabel' => 'Explore our courses',
        'actionUrl' => route('courses.index'),
    ])
    <div class="mt-8 grid gap-5 sm:grid-cols-3">
        @foreach ([['🎯', 'Our mission', 'Help learners communicate clearly and confidently in English.'], ['👩‍🏫', 'Supportive guidance', 'Learn through guided lessons, feedback and regular speaking practice.'], ['📈', 'Practice & progress', 'Track progress in grammar, vocabulary, listening, speaking and writing.'],] as [$icon, $title, $text])
            <article class="aec-motion-card rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-orange-50 text-2xl">{{ $icon }}</div>
                <h2 class="mt-4 text-lg font-extrabold text-slate-900">{{ $title }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $text }}</p>
            </article>
        @endforeach
    </div>
    <section class="mt-7 grid gap-6 overflow-hidden rounded-3xl bg-[#103b46] p-6 text-white sm:p-9 lg:grid-cols-[.8fr_1.2fr] lg:p-12">
        <div><p class="text-xs font-extrabold uppercase tracking-[.16em] text-orange-200">A clear path forward</p><h2 class="mt-3 text-2xl font-extrabold sm:text-3xl">Why students choose us</h2><p class="mt-3 text-sm leading-7 text-slate-300">Build confidence through structured learning, regular practice and teacher support.</p></div>
        <ul class="grid gap-3 sm:grid-cols-2">
            <li class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm leading-6">✓ Structured lessons for beginner to advanced learners</li>
            <li class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm leading-6">✓ Grammar, vocabulary and communication practice with feedback</li>
            <li class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm leading-6">✓ Practical worksheets, notes and vocabulary resources</li>
            <li class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm leading-6">✓ Progress tracking and course completion certificates</li>
            <li class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm leading-6 sm:col-span-2">✓ Teacher guidance and learner support through official center channels</li>
        </ul>
    </section>
</div>
@endsection
