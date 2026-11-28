@extends('layouts.app')
@section('title', 'Contact')
@section('content')
<div class="aec-public-page mx-auto max-w-7xl">
    @include('partials.public-page-hero', [
        'eyebrow' => 'We are here to help',
        'title' => 'Talk to our team',
        'description' => 'Questions about a course, payment or your account? Send us a message and our team will help you find the next step.',
    ])
    <div class="mt-7 grid gap-6 lg:grid-cols-[.8fr_1.2fr]">
        <aside class="rounded-3xl bg-[#103b46] p-6 text-white sm:p-8">
            <p class="text-xs font-extrabold uppercase tracking-[.16em] text-orange-200">Contact information</p>
            <h2 class="mt-3 text-2xl font-extrabold">How can we help?</h2>
            <p class="mt-2 text-sm leading-6 text-slate-300">Choose the channel that works best for you. We usually reply within a few hours.</p>
            <div class="mt-7 space-y-3 text-sm">
                <p class="rounded-2xl border border-white/10 bg-white/5 p-4"><b class="block text-xs uppercase tracking-wide text-slate-300">Email</b><span class="mt-1 block break-all">{{ \App\Models\Setting::get('site_email', 'support@example.com') }}</span></p>
                <p class="rounded-2xl border border-white/10 bg-white/5 p-4"><b class="block text-xs uppercase tracking-wide text-slate-300">Phone</b><span class="mt-1 block">{{ \App\Models\Setting::get('site_phone', '+880 1700-000000') }}</span></p>
                <p class="rounded-2xl border border-white/10 bg-white/5 p-4"><b class="block text-xs uppercase tracking-wide text-slate-300">Address</b><span class="mt-1 block">{{ \App\Models\Setting::get('site_address', 'Dhaka, Bangladesh') }}</span></p>
            </div>
        </aside>
        <form method="POST" action="{{ route('contact.send') }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            <div class="absolute -left-[10000px] top-auto h-px w-px overflow-hidden" aria-hidden="true"><label>Leave this field empty<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="mb-2"><p class="text-xs font-extrabold uppercase tracking-[.16em] text-orange-700">Send us a message</p><h2 class="mt-1 text-xl font-extrabold text-slate-900">We will get back to you</h2></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Name</label><input name="name" value="{{ old('name', auth()->user()->name ?? '') }}" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Email</label><input type="email" name="email" value="{{ old('email', auth()->user()->email ?? '') }}" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none"></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm font-semibold text-slate-700">Phone (optional)</label><input name="phone" value="{{ old('phone', auth()->user()->phone ?? '') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></div>
                <div><label class="mb-1 block text-sm font-semibold text-slate-700">Subject (optional)</label><input name="subject" value="{{ old('subject') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></div>
            </div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Message</label><textarea name="message" rows="5" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">{{ old('message') }}</textarea></div>
            <button class="w-full rounded-full bg-emerald-700 px-4 py-3 font-bold text-white transition hover:bg-emerald-800">Send message</button>
        </form>
    </div>
    <section class="mt-7 rounded-3xl border border-orange-100 bg-orange-50/80 p-6 sm:p-8">
        <div class="mb-4"><p class="text-xs font-extrabold uppercase tracking-[.16em] text-orange-700">Stay in the loop</p><h2 class="mt-1 text-xl font-extrabold text-slate-900">Get course news and learning updates</h2><p class="mt-1 text-sm text-slate-600">Subscribe by email. We will send a confirmation link before adding you to the newsletter.</p></div>
        @include('home.newsletter-form')
    </section>
</div>
@endsection
