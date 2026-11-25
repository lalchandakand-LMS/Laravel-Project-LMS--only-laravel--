@extends('layouts.app')
@section('title', 'About Alom’s English Center')
@section('meta_description', 'Meet Alom’s English Center: practical English lessons, speaking practice, grammar, vocabulary and teacher-guided learning.')
@section('content')
<div class="mx-auto max-w-4xl px-4 py-16">
    <h1 class="text-4xl font-extrabold tracking-tight text-slate-900">About Alom's English Center</h1>
    <p class="mt-4 text-lg text-slate-600">Alom's English Center helps learners build practical English skills through structured lessons, guided practice and supportive teaching. Our goal is to make quality English education accessible to learners at every level.</p>

    <div class="mt-10 grid gap-6 sm:grid-cols-3">
        @foreach ([['🎯', 'Our mission', 'Help learners communicate clearly and confidently in English.'], ['👩‍🏫', 'Expert teachers', 'Learn through guided lessons, feedback and regular speaking practice.'], ['📈', 'Proven results', 'Track progress in grammar, vocabulary, listening, speaking and writing.'],] as [$icon, $title, $text])
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-3xl">{{ $icon }}</div>
                <h3 class="mt-3 font-bold text-slate-900">{{ $title }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-12 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h2 class="text-2xl font-bold text-slate-900">Why students choose us</h2>
        <ul class="mt-4 space-y-3 text-slate-600">
            <li class="flex gap-3"><span class="text-emerald-700">✔</span> Structured English lessons for beginner to advanced learners</li>
            <li class="flex gap-3"><span class="text-emerald-700">✔</span> Grammar, vocabulary and communication practice with feedback</li>
            <li class="flex gap-3"><span class="text-emerald-700">✔</span> Practical worksheets, notes and vocabulary resources</li>
            <li class="flex gap-3"><span class="text-emerald-700">✔</span> Learning progress tracking and course completion certificates</li>
            <li class="flex gap-3"><span class="text-emerald-700">✔</span> Teacher guidance and learner support through official center channels</li>
        </ul>
    </div>
</div>
@endsection
