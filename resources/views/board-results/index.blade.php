@extends('layouts.app')
@section('title', $title.' | '.config('app.name'))
@section('meta_description', 'View student board examination results and achievements from Alom’s English Center.')
@section('content')
<section class="bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 py-16 text-white sm:py-20">
    <div class="mx-auto max-w-5xl px-4 text-center">
        <p class="text-xs font-bold uppercase tracking-[.22em] text-orange-300">Academic achievements {{ $year }}</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-5xl">{{ $title }}</h1>
        @if ($subtitle)<p class="mx-auto mt-4 max-w-3xl text-base leading-7 text-blue-100">{{ $subtitle }}</p>@endif
    </div>
</section>
<section class="mx-auto max-w-5xl px-4 py-12 sm:py-16">
    @if (filled($body))
        <article class="whitespace-pre-line rounded-3xl border border-slate-200 bg-white p-6 text-base leading-8 text-slate-700 shadow-sm sm:p-10">{{ $body }}</article>
    @else
        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center">
            <h2 class="text-xl font-bold text-slate-900">Results will be published here</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Our academic team has not published board results yet. Please check back soon.</p>
        </div>
    @endif
    <div class="mt-8 text-center"><a href="{{ route('courses.index') }}" class="inline-flex rounded-xl bg-orange-600 px-6 py-3 font-bold text-white hover:bg-orange-700">Explore courses</a></div>
</section>
@endsection
