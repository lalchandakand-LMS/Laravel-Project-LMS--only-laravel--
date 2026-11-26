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
                @php($currentLogo = $settings['logo'] ?? '')
                <img src="{{ $currentLogo ? (str_starts_with($currentLogo, 'https://') ? $currentLogo : asset('storage/'.$currentLogo)) : asset('images/aec-logo.svg') }}" alt="Current logo" class="mb-3 h-16 w-16 rounded-lg border border-slate-200 object-contain">
                <label class="mb-1 block text-xs font-semibold text-slate-600">Upload logo</label>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
                <label class="mb-1 mt-3 block text-xs font-semibold text-slate-600">Or logo image URL (HTTPS)</label>
                <input type="url" name="logo_url" value="{{ old('logo_url', str_starts_with((string) $currentLogo, 'https://') ? $currentLogo : '') }}" placeholder="https://example.com/logo.png" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @if (!empty($currentLogo))<label class="mt-2 flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>@endif
                <p class="mt-1 text-xs text-slate-500">Upload takes priority. PNG, JPG or WebP; max 2 MB.</p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2"><div><h3 class="font-bold text-slate-900">Dashboard welcome banner</h3><p class="mt-1 text-xs text-slate-500">Customize the shared welcome banner shown across admin, student, teacher, branch and guardian dashboards.</p></div>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="dashboard_banner_enabled" value="1" @checked(($settings['dashboard_banner_enabled'] ?? '1') === '1')> Show banner</label></div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Banner heading</label><input name="dashboard_banner_title" value="{{ old('dashboard_banner_title', $settings['dashboard_banner_title'] ?? 'Welcome back!') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Subtitle</label><input name="dashboard_banner_subtitle" value="{{ old('dashboard_banner_subtitle', $settings['dashboard_banner_subtitle'] ?? 'Here is what is happening at your academy today.') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Button text</label><input name="dashboard_banner_button_text" value="{{ old('dashboard_banner_button_text', $settings['dashboard_banner_button_text'] ?? 'Manage courses') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Button URL (optional)</label><input name="dashboard_banner_button_url" value="{{ old('dashboard_banner_button_url', $settings['dashboard_banner_button_url'] ?? '') }}" placeholder="https://... or /admin/courses" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div class="sm:col-span-2"><label class="mb-2 block text-sm font-semibold text-slate-700">Banner image</label>
                @php($currentBanner = $settings['dashboard_banner'] ?? '')
                @if ($currentBanner)<img src="{{ str_starts_with($currentBanner, 'https://') ? $currentBanner : asset('storage/'.$currentBanner) }}" alt="Current dashboard banner" class="mb-3 h-36 w-full rounded-xl object-cover">@endif
                <label class="mb-1 block text-xs font-semibold text-slate-600">Upload banner image</label>
                <input type="file" name="dashboard_banner" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
                <label class="mb-1 mt-3 block text-xs font-semibold text-slate-600">Or banner image URL (HTTPS)</label>
                <input type="url" name="dashboard_banner_url" value="{{ old('dashboard_banner_url', str_starts_with((string) $currentBanner, 'https://') ? $currentBanner : '') }}" placeholder="https://example.com/dashboard-banner.webp" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @if ($currentBanner)<label class="mt-2 flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" name="remove_dashboard_banner" value="1"> Remove banner image</label>@endif
                <p class="mt-1 text-xs text-slate-500">Upload takes priority. PNG, JPG or WebP; max 5 MB. Leave both empty to keep the current banner.</p>
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
        <div class="mt-4 grid gap-5 md:grid-cols-2">
        @foreach ([
            'student' => ['stats' => 'Statistics', 'courses' => 'Course progress', 'exams' => 'Upcoming exams', 'activity' => 'Recent activity'],
            'teacher' => ['stats' => 'Statistics', 'trend' => 'Enrolment trend', 'upcoming' => 'Upcoming live classes', 'courses' => 'Top courses', 'students' => 'Latest students'],
            'branch' => ['stats' => 'Branch statistics', 'collection' => 'Collection chart', 'courses' => 'Top courses', 'students' => 'New students', 'orders' => 'Recent orders'],
            'guardian' => ['children' => 'Children summary', 'orders' => 'Recent purchases'],
        ] as $role => $widgets)
        @endforeach
                </div>
            </div>
        @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <h3 class="font-bold text-slate-900">Homepage sections & content</h3>
        <p class="mt-1 text-xs text-slate-500">Control which public homepage sections appear. Live classes, exams and teachers are populated from your LMS records. Board-result content is shown only when you publish verified information here.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['live_classes' => 'Upcoming live classes', 'exams' => 'Upcoming exams', 'teachers' => 'Teacher profiles', 'board_results' => 'Board results / achievements', 'admission' => 'Online & classroom admission CTA'] as $key => $label)
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3 text-sm"><input type="checkbox" name="homepage_show_{{ $key }}" value="1" @checked(($settings['homepage_show_'.$key] ?? '1') === '1')> {{ $label }}</label>
            @endforeach
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-4 md:col-span-2">
                <h4 class="font-bold text-slate-800">Board results / achievements (optional)</h4>
                <p class="mt-1 text-xs text-slate-500">Enter only verified results. Leave the body empty to keep this section hidden.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <input name="homepage_board_results_title" value="{{ old('homepage_board_results_title', $settings['homepage_board_results_title'] ?? 'Our students’ achievements') }}" placeholder="Section title" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="homepage_board_results_year" value="{{ old('homepage_board_results_year', $settings['homepage_board_results_year'] ?? '') }}" placeholder="Year / session" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="homepage_board_results_subtitle" value="{{ old('homepage_board_results_subtitle', $settings['homepage_board_results_subtitle'] ?? '') }}" placeholder="Short subtitle" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2">
                    <textarea name="homepage_board_results_body" rows="4" placeholder="Verified board result details, achievements and statistics..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2">{{ old('homepage_board_results_body', $settings['homepage_board_results_body'] ?? '') }}</textarea>
                </div>
            </div>
            <div class="rounded-xl bg-slate-50 p-4 md:col-span-2">
                <h4 class="font-bold text-slate-800">Admission call-to-action</h4>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <input name="homepage_admission_title" value="{{ old('homepage_admission_title', $settings['homepage_admission_title'] ?? 'Join an online or classroom batch') }}" placeholder="Heading" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="homepage_admission_button_text" value="{{ old('homepage_admission_button_text', $settings['homepage_admission_button_text'] ?? 'Apply for admission') }}" placeholder="Button text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="homepage_admission_subtitle" value="{{ old('homepage_admission_subtitle', $settings['homepage_admission_subtitle'] ?? 'Choose a course and start learning with Alom’s English Center.') }}" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="homepage_admission_button_url" value="{{ old('homepage_admission_button_url', $settings['homepage_admission_button_url'] ?? '/courses') }}" placeholder="Button URL (e.g. /courses or /contact)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2" data-settings-group="design">
        <h3 class="font-bold text-slate-900">Homepage, footer & developer design</h3>
        <p class="mt-1 text-sm text-slate-500">Customize section titles, backgrounds and text colors. Leave content fields blank to use the built-in defaults.</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'homepage_learning_title' => ['Learning path heading', 'text', 'Choose your learning path'],
                'homepage_learning_subtitle' => ['Learning path description', 'text', 'Choose a course to improve your skills'],
                'homepage_testimonial_title' => ['Testimonial heading', 'text', 'What our students say'],
                'footer_copyright' => ['Footer copyright text', 'text', 'All rights reserved.'],
                'developer_name' => ['Developer / agency name', 'text', ''],
                'developer_url' => ['Developer website URL', 'url', ''],
                'developer_text' => ['Developer credit text', 'text', 'Developed by'],
            ] as $key => [$label, $type, $placeholder])
                <div><label class="mb-1 block text-sm font-semibold text-slate-700">{{ $label }}</label><input type="{{ $type }}" name="{{ $key }}" value="{{ old($key, $settings[$key] ?? '') }}" placeholder="{{ $placeholder }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            @endforeach
        </div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'homepage_learning_bg' => ['Learning path background', '#ffffff'],
                'homepage_learning_text' => ['Learning path text', '#0f172a'],
                'homepage_testimonial_bg' => ['Testimonial background', '#f1f5f9'],
                'homepage_testimonial_text' => ['Testimonial text', '#0f172a'],
                'homepage_stats_bg' => ['Statistics background', '#047857'],
                'homepage_stats_text' => ['Statistics text', '#ffffff'],
                'footer_bg' => ['Footer background', '#ffffff'],
                'footer_text' => ['Footer text', '#475569'],
                'footer_heading' => ['Footer heading', '#0f172a'],
                'developer_bg' => ['Developer strip background', '#0f172a'],
                'developer_text_color' => ['Developer strip text', '#ffffff'],
            ] as $key => [$label, $default])
                <div><label class="mb-1 block text-sm font-semibold text-slate-700">{{ $label }}</label><input type="color" name="{{ $key }}" value="{{ old($key, $settings[$key] ?? $default) }}" class="h-11 w-full rounded-lg border border-slate-300 p-1"></div>
            @endforeach
        </div>
        <label class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="developer_show" value="1" @checked(($settings['developer_show'] ?? '0') === '1')> Show developer credit strip on website</label>
    </section>


    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2" data-settings-group="loading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h3 class="font-bold text-slate-900">Brand page loading system</h3><p class="mt-1 text-sm text-slate-500">Show a branded loading screen while public pages are loading. This does not block admin dashboard pages.</p></div>
            <label class="inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="brand_loader_enabled" value="1" @checked(($settings['brand_loader_enabled'] ?? '0') === '1')> Enable loading screen</label>
        </div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Loading message</label><input name="brand_loader_message" value="{{ old('brand_loader_message', $settings['brand_loader_message'] ?? 'Preparing your learning experience...') }}" maxlength="120" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Loader style</label><select name="brand_loader_style" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="ring" @selected(($settings['brand_loader_style'] ?? 'ring') === 'ring')>Rotating ring</option><option value="dots" @selected(($settings['brand_loader_style'] ?? '') === 'dots')>Animated dots</option><option value="pulse" @selected(($settings['brand_loader_style'] ?? '') === 'pulse')>Brand pulse</option></select></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Minimum display time (ms)</label><input type="number" min="0" max="2000" step="100" name="brand_loader_min_time" value="{{ old('brand_loader_min_time', $settings['brand_loader_min_time'] ?? '250') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            @foreach (['brand_loader_bg' => ['Screen background', '#ffffff'], 'brand_loader_color' => ['Spinner / accent color', '#047857'], 'brand_loader_text_color' => ['Message text color', '#0f172a']] as $key => [$label, $default])
                <div><label class="mb-1 block text-sm font-semibold text-slate-700">{{ $label }}</label><input type="color" name="{{ $key }}" value="{{ old($key, $settings[$key] ?? $default) }}" class="h-11 w-full rounded-lg border border-slate-300 p-1"></div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-slate-500">The current website logo and site name are used automatically. Respects reduced-motion accessibility preferences and includes a failsafe timeout.</p>
    </section>

    <div class="lg:col-span-2"><button class="rounded-lg bg-emerald-700 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Save all settings</button></div>
</form>

@push('scripts')
<script>
(function () {
    const form = document.querySelector('form[action="{{ route('admin.settings.update') }}"]');
    if (!form) return;
    const sections = Array.from(form.querySelectorAll(':scope > section'));
    const tabs = [
        ['general','General & Branding'],['dashboard','Dashboard'],['homepage','Homepage'],['design','Design & Footer'],['loading','Brand Loading'],['menu','Menu Settings'],['slider','Slider Settings']
    ];
    const nav = document.createElement('div');
    nav.className = 'mb-6 flex flex-wrap gap-2 lg:col-span-2';
    nav.setAttribute('role','tablist');
    const panels = {};
    tabs.forEach(([id,label]) => {
        const panel=document.createElement('div'); panel.dataset.tabPanel=id; panel.className='grid gap-6 lg:grid-cols-2 lg:col-span-2'; panels[id]=panel;
        const button=document.createElement('button'); button.type='button'; button.textContent=label; button.dataset.tab=id; button.setAttribute('role','tab'); button.className='rounded-xl border px-4 py-2.5 text-sm font-bold';
        button.addEventListener('click',()=>activate(id)); nav.appendChild(button);
    });
    function group(section) {
        if (section.dataset.settingsGroup) return section.dataset.settingsGroup;
        const title=(section.querySelector('h3')?.textContent || '').toLowerCase();
        if (title.includes('dashboard')) return 'dashboard';
        if (title.includes('homepage')) return 'homepage';
        if (title.includes('brand identity') || title.includes('general') || title.includes('payments') || title.includes('social')) return 'general';
        return 'general';
    }
    sections.forEach(section=>panels[group(section)].appendChild(section));
    const save=form.querySelector(':scope > div:last-child');
    form.prepend(nav);
    Object.values(panels).forEach(panel=>{form.insertBefore(panel,save);});
    const linkPanel=(id,title,href,description)=>{
        const card=document.createElement('section'); card.className='rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2';
        card.innerHTML='<h3 class="font-bold text-slate-900">'+title+'</h3><p class="mt-2 text-sm text-slate-600">'+description+'</p><a class="mt-4 inline-flex rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white" href="'+href+'">Open '+title+'</a>';
        panels[id].appendChild(card);
    };
    linkPanel('menu','Menu Settings',"{{ route('admin.menu-settings.index') }}",'Manage header and footer links, dropdowns, ordering and desktop/mobile visibility.');
    linkPanel('slider','Slider Settings',"{{ route('admin.sliders.index') }}",'Create, edit, publish, hide and reorder homepage slides, including images and call-to-action buttons.');
    function activate(id) {
        Object.entries(panels).forEach(([key,panel])=>panel.hidden=key!==id);
        nav.querySelectorAll('button').forEach(button=>{const active=button.dataset.tab===id;button.setAttribute('aria-selected',active?'true':'false');button.className='rounded-xl border px-4 py-2.5 text-sm font-bold '+(active?'border-emerald-700 bg-emerald-700 text-white':'border-slate-200 bg-white text-slate-700');});
        try{localStorage.setItem('aec-site-settings-tab',id)}catch(e){}
    }
    activate((()=>{try{return localStorage.getItem('aec-site-settings-tab')||'general'}catch(e){return 'general'}})());
})();
</script>
@endpush
\n@endsection
