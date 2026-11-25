@extends('layouts.admin')
@section('title', 'Settings')
@section('heading', 'Site settings')
@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="grid max-w-5xl gap-6 lg:grid-cols-2">
    @csrf
    @php
        $fields = [
            'General' => [
                ['site_name', 'Site name'], ['site_tagline', 'Tagline'],
                ['site_email', 'Support email'], ['site_phone', 'Support phone'],
                ['site_address', 'Address'], ['currency', 'Currency symbol'],
            ],
            'Payments' => [
                ['bkash_number', 'bKash number'], ['nagad_number', 'Nagad number'],
            ],
            'Social' => [
                ['facebook', 'Facebook URL'], ['youtube', 'YouTube URL'], ['instagram', 'Instagram URL'],
            ],
        ];
    @endphp

    @foreach ($fields as $group => $items)
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-bold text-slate-900">{{ $group }}</h3>
            <div class="mt-4 space-y-3">
                @foreach ($items as [$key, $label])
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">{{ $label }}</label>
                        <input name="{{ $key }}" type="{{ in_array($key, ['facebook', 'youtube', 'instagram']) ? 'url' : ($key === 'site_email' ? 'email' : 'text') }}" value="{{ old($key, $settings[$key] ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <h3 class="font-bold text-slate-900">About & branding</h3>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">About text</label>
                <textarea name="about_text" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('about_text', $settings['about_text'] ?? '') }}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Certificate signature name</label>
                <input name="certificate_signature" value="{{ old('certificate_signature', $settings['certificate_signature'] ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <label class="mt-3 mb-1 block text-sm font-semibold text-slate-700">Logo</label>
                @if (!empty($settings['logo']))
                    <img src="{{ asset('storage/'.$settings['logo']) }}" alt="Current site logo" class="mb-2 h-16 w-16 rounded-lg border border-slate-200 object-contain">
                @else
                    <img src="{{ asset('images/aec-logo.svg') }}" alt="Alom's English Center logo" class="mb-2 h-16 w-16 rounded-lg border border-slate-200 object-contain">
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
                <p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP; maximum 2 MB. Uploading a logo replaces the default AEC logo.</p>
            </div>
        </div>
    </section>

    <div class="lg:col-span-2">
        <button class="rounded-lg bg-emerald-700 px-6 py-3 text-sm font-bold text-white">Save settings</button>
    </div>
</form>
@endsection
