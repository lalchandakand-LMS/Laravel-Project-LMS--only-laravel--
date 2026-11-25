@extends('layouts.admin')
@section('title', 'Settings')
@section('heading', 'Site settings')
@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="grid max-w-6xl gap-6 lg:grid-cols-2">
    @csrf
    @php
        $fields = [
            'General' => [
                ['site_name', 'Site name'], ['site_tagline', 'Tagline'],
                ['site_email', 'Support email'], ['site_phone', 'Support phone'],
                ['site_address', 'Address'], ['currency', 'Currency symbol'],
            ],
            'Payments' => [['bkash_number', 'bKash number'], ['nagad_number', 'Nagad number']],
            'Social' => [['facebook', 'Facebook URL'], ['youtube', 'YouTube URL'], ['instagram', 'Instagram URL']],
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
        <h3 class="font-bold text-slate-900">Brand identity</h3>
        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">About text</label>
                <textarea name="about_text" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('about_text', $settings['about_text'] ?? '') }}</textarea>
                <label class="mt-4 mb-1 block text-sm font-semibold text-slate-700">Certificate signature name</label>
                <input name="certificate_signature" value="{{ old('certificate_signature', $settings['certificate_signature'] ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Logo</label>
                <img src="{{ !empty($settings['logo']) ? asset('storage/'.$settings['logo']) : asset('images/aec-logo.svg') }}" alt="Current logo" class="mb-3 h-16 w-16 rounded-lg border border-slate-200 object-contain">
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
                @if (!empty($settings['logo']))<label class="mt-2 flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>@endif
                <p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP; maximum 2 MB.</p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2"><div><h3 class="font-bold text-slate-900">Dashboard welcome banner</h3><p class="mt-1 text-xs text-slate-500">Customize the banner shown at the top of the admin dashboard.</p></div>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="dashboard_banner_enabled" value="1" @checked(($settings['dashboard_banner_enabled'] ?? '1') === '1')> Show banner</label></div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Banner heading</label><input name="dashboard_banner_title" value="{{ old('dashboard_banner_title', $settings['dashboard_banner_title'] ?? 'Welcome back!') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Subtitle</label><input name="dashboard_banner_subtitle" value="{{ old('dashboard_banner_subtitle', $settings['dashboard_banner_subtitle'] ?? 'Here is what is happening at your academy today.') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Button text</label><input name="dashboard_banner_button_text" value="{{ old('dashboard_banner_button_text', $settings['dashboard_banner_button_text'] ?? 'Manage courses') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Button URL (optional)</label><input name="dashboard_banner_button_url" value="{{ old('dashboard_banner_button_url', $settings['dashboard_banner_button_url'] ?? '') }}" placeholder="https://... or /admin/courses" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div class="sm:col-span-2"><label class="mb-2 block text-sm font-semibold text-slate-700">Banner image</label>
                @if (!empty($settings['dashboard_banner']))<img src="{{ asset('storage/'.$settings['dashboard_banner']) }}" alt="Current dashboard banner" class="mb-3 h-36 w-full rounded-xl object-cover">@endif
                <input type="file" name="dashboard_banner" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
                @if (!empty($settings['dashboard_banner']))<label class="mt-2 flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" name="remove_dashboard_banner" value="1"> Remove banner image</label>@endif
                <p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP; maximum 5 MB. Without an image, the banner uses a gradient background.</p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <h3 class="font-bold text-slate-900">Dashboard appearance & widgets</h3>
        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Primary brand color</label><input type="color" name="dashboard_primary_color" value="{{ old('dashboard_primary_color', $settings['dashboard_primary_color'] ?? '#059669') }}" class="h-11 w-full rounded-lg border border-slate-300 p-1"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Secondary brand color</label><input type="color" name="dashboard_secondary_color" value="{{ old('dashboard_secondary_color', $settings['dashboard_secondary_color'] ?? '#0f172a') }}" class="h-11 w-full rounded-lg border border-slate-300 p-1"></div>
        </div>
        <p class="mt-5 text-sm font-bold text-slate-800">Visible dashboard sections</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            @foreach (['stats' => 'Statistics cards', 'chart' => 'Revenue chart', 'courses' => 'Top courses', 'orders' => 'Recent orders', 'enrollments' => 'New enrollments', 'reviews' => 'Pending reviews'] as $key => $label)
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3 text-sm"><input type="checkbox" name="dashboard_widget_{{ $key }}" value="1" @checked(($settings['dashboard_widget_'.$key] ?? '1') === '1')> {{ $label }}</label>
            @endforeach
        </div>
    </section>


    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <h3 class="font-bold text-slate-900">Student, teacher, branch & guardian dashboard widgets</h3>
        <p class="mt-1 text-xs text-slate-500">Choose which sections are visible on each portal. New settings are enabled by default.</p>
        @php
        $roleWidgets = [
            'student' => ['stats' => 'Statistics', 'courses' => 'Course progress', 'exams' => 'Upcoming exams', 'activity' => 'Recent activity'],
            'teacher' => ['stats' => 'Statistics', 'trend' => 'Enrolment trend', 'upcoming' => 'Upcoming live classes', 'courses' => 'Top courses', 'students' => 'Latest students'],
            'branch' => ['stats' => 'Branch statistics', 'collection' => 'Collection chart', 'courses' => 'Top courses', 'students' => 'New students', 'orders' => 'Recent orders'],
            'guardian' => ['children' => 'Children summary', 'orders' => 'Recent purchases'],
        ];
        @endphp
        <div class="mt-4 grid gap-5 md:grid-cols-2">
        @foreach ($roleWidgets as $role => $widgets)
            <div class="rounded-xl border border-slate-200 p-4">
                <h4 class="mb-3 font-bold capitalize text-slate-800">{{ $role }} dashboard</h4>
                <div class="space-y-2">
                @foreach ($widgets as $key => $label)
                    @php($settingKey = 'dashboard_widget_'.$role.'_'.$key)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="{{ $settingKey }}" value="1" @checked(($settings[$settingKey] ?? '1') === '1')>
                        {{ $label }}
                    </label>
                @endforeach
                </div>
            </div>
        @endforeach
        </div>
    </section>

    <div class="lg:col-span-2"><button class="rounded-lg bg-emerald-700 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Save all settings</button></div>
</form>
@endsection
