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
                ['site_address', 'Address'], ['company_license', 'Company license / registration'], ['currency', 'Currency symbol'],
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
                <img src="{{ !empty($settings['logo']) ? media_url($settings['logo']) : asset('images/aec-logo.svg') }}" alt="Current logo" class="mb-3 h-16 w-16 rounded-lg border border-slate-200 object-contain">
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
                <label class="mt-2 block text-xs font-semibold text-slate-600">Or use an image URL</label>
                <input type="url" name="logo_url" value="{{ old('logo_url', str_starts_with((string) ($settings['logo'] ?? ''), 'http') ? $settings['logo'] : '') }}" placeholder="https://example.com/logo.png" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <a href="{{ route('admin.media.index') }}" class="mt-2 inline-block text-xs font-semibold text-emerald-700">Open Media Library →</a>
                @if (!empty($settings['logo']))<label class="mt-2 flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>@endif
                <p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP; maximum 2 MB.</p>
            </div>
        </div>
        <div class="mt-5 rounded-xl border border-orange-200 bg-orange-50/60 p-4">
            <h4 class="font-bold text-slate-900">Public website brand colors</h4>
            <p class="mt-1 text-xs text-slate-600">These colors control public navigation, buttons, links, badges and accent highlights. Homepage section backgrounds can still be customized separately below.</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm font-semibold text-slate-700">Primary brand color</label><input type="color" name="site_primary_color" value="{{ old('site_primary_color', $settings['site_primary_color'] ?? '#06b6d4') }}" class="h-11 w-full rounded-lg border border-slate-300 p-1"></div>
                <div><label class="mb-1 block text-sm font-semibold text-slate-700">Secondary / hover color</label><input type="color" name="site_secondary_color" value="{{ old('site_secondary_color', $settings['site_secondary_color'] ?? '#0d9488') }}" class="h-11 w-full rounded-lg border border-slate-300 p-1"></div>
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
                @if (!empty($settings['dashboard_banner']))<img src="{{ media_url($settings['dashboard_banner']) }}" alt="Current dashboard banner" class="mb-3 h-36 w-full rounded-xl object-cover">@endif
                <input type="file" name="dashboard_banner" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
                <label class="mt-2 block text-xs font-semibold text-slate-600">Or use an image URL</label>
                <input type="url" name="dashboard_banner_url" value="{{ old('dashboard_banner_url', str_starts_with((string) ($settings['dashboard_banner'] ?? ''), 'http') ? $settings['dashboard_banner'] : '') }}" placeholder="https://example.com/banner.webp" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <a href="{{ route('admin.media.index') }}" class="mt-2 inline-block text-xs font-semibold text-emerald-700">Choose from Media Library (copy URL) →</a>
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

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
        <h3 class="font-bold text-slate-900">Homepage sections & content</h3>
        <p class="mt-1 text-xs text-slate-500">Control which public homepage sections appear. Live classes, exams and teachers are populated from your LMS records. Board-result content is shown only when you publish verified information here.</p>
        <div class="mt-5 rounded-xl border border-orange-200 bg-orange-50/60 p-4">
            <h4 class="font-bold text-slate-900">Homepage section display order</h4>
            <p class="mt-1 text-xs text-slate-600">Drag sections or use the up/down controls. Hidden sections keep their place and appear in this order when enabled again.</p>
            <input type="hidden" id="homepage-section-order-value" name="homepage_section_order" value="{{ old('homepage_section_order', $settings['homepage_section_order'] ?? '[&quot;hero&quot;,&quot;categories&quot;,&quot;featured&quot;,&quot;online_batches&quot;,&quot;stats&quot;,&quot;latest&quot;,&quot;live_classes&quot;,&quot;exams&quot;,&quot;teachers&quot;,&quot;board_results&quot;,&quot;reviews&quot;,&quot;ebooks&quot;,&quot;admission&quot;]') }}">
            <ol id="homepage-section-order-list" class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (['hero' => 'Hero slider / banner', 'categories' => 'Learning paths / categories', 'featured' => 'Featured courses', 'online_batches' => 'Online batches', 'stats' => 'Trust statistics', 'latest' => 'Latest courses', 'live_classes' => 'Upcoming live classes', 'exams' => 'Upcoming exams', 'teachers' => 'Teacher profiles', 'board_results' => 'Board results / achievements', 'reviews' => 'Student reviews', 'ebooks' => 'E-books & notes', 'admission' => 'Online & classroom admission CTA'] as $key => $label)
                    <li draggable="true" data-section-key="{{ $key }}" class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white p-2.5 text-sm">
                        <span class="flex min-w-0 items-center gap-2"><span aria-hidden="true" class="cursor-grab text-slate-400">⠿</span><span>{{ $label }}</span></span>
                        <span class="flex shrink-0 gap-1">
                            <button type="button" data-move="up" aria-label="Move {{ $label }} up" class="rounded border border-slate-200 px-2 py-1 text-xs font-bold hover:bg-slate-100">↑</button>
                            <button type="button" data-move="down" aria-label="Move {{ $label }} down" class="rounded border border-slate-200 px-2 py-1 text-xs font-bold hover:bg-slate-100">↓</button>
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['hero' => 'Hero slider / banner', 'categories' => 'Learning paths / categories', 'featured' => 'Featured courses', 'online_batches' => 'Online batches', 'stats' => 'Trust statistics', 'latest' => 'Latest courses', 'live_classes' => 'Upcoming live classes', 'exams' => 'Upcoming exams', 'teachers' => 'Teacher profiles', 'board_results' => 'Board results / achievements', 'reviews' => 'Student reviews', 'ebooks' => 'E-books & notes', 'admission' => 'Online & classroom admission CTA'] as $key => $label)
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3 text-sm"><input type="checkbox" name="homepage_show_{{ $key }}" value="1" @checked((in_array($key, ['hero','categories','online_batches','reviews'], true) ? ($settings['homepage_show_'.$key] ?? '1') : ($settings['homepage_show_'.$key] ?? '0')) === '1')> {{ $label }}</label>
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
        <h3 class="font-bold text-slate-900">Admin Design Studio</h3>
        <p class="mt-1 text-sm text-slate-500">Customize the Admin workspace palette, card corners, brand text and sidebar promotion panel.</p>
        <div class="mt-4 grid gap-4 rounded-xl border border-orange-200 bg-orange-50/60 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Admin theme preset</label><select name="admin_theme_preset" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">@foreach (['navy'=>'Royal navy','orange'=>'Orange','teal'=>'Teal','purple'=>'Purple'] as $value=>$label)<option value="{{ $value }}" @selected(($settings['admin_theme_preset'] ?? 'navy') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Card corner radius</label><select name="admin_card_radius" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">@foreach ([10,14,18,22] as $radius)<option value="{{ $radius }}" @selected((int) ($settings['admin_card_radius'] ?? 18) === $radius)>{{ $radius }} px</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Admin brand title</label><input name="admin_brand_title" value="{{ old('admin_brand_title', $settings['admin_brand_title'] ?? "Alom's LMS") }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Brand subtitle</label><input name="admin_brand_subtitle" value="{{ old('admin_brand_subtitle', $settings['admin_brand_subtitle'] ?? 'Admin workspace') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div class="sm:col-span-2"><label class="mb-1 block text-sm font-semibold text-slate-700">Sidebar promo title</label><input name="admin_sidebar_promo_title" value="{{ old('admin_sidebar_promo_title', $settings['admin_sidebar_promo_title'] ?? 'Grow your academy') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div class="sm:col-span-2"><label class="mb-1 block text-sm font-semibold text-slate-700">Sidebar promo message</label><input name="admin_sidebar_promo_body" value="{{ old('admin_sidebar_promo_body', $settings['admin_sidebar_promo_body'] ?? 'Keep your courses, learners and live classes moving forward.') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Promo button text</label><input name="admin_sidebar_promo_button" value="{{ old('admin_sidebar_promo_button', $settings['admin_sidebar_promo_button'] ?? 'Explore settings') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-sm font-semibold text-slate-700">Promo button URL</label><input name="admin_sidebar_promo_url" value="{{ old('admin_sidebar_promo_url', $settings['admin_sidebar_promo_url'] ?? '/admin/settings') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 sm:col-span-2"><input type="checkbox" name="admin_sidebar_promo_enabled" value="1" @checked(($settings['admin_sidebar_promo_enabled'] ?? '1') === '1')> Show sidebar promotion panel</label>
        </div>
        <div class="mt-6 border-t border-slate-200 pt-6">
        <h3 class="font-bold text-slate-900">Homepage, footer & developer design</h3>
        <p class="mt-1 text-sm text-slate-500">Customize section titles, backgrounds and text colors. Leave content fields blank to use the built-in defaults.</p>
        <div class="mt-5 rounded-xl border border-orange-200 bg-orange-50/60 p-4">
            <h4 class="font-bold text-slate-900">Homepage section layout</h4>
            <p class="mt-1 text-sm text-slate-600">Choose a responsive carousel or a traditional grid for each content section. The hero slider remains managed separately in Slider Settings.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    'homepage_learning_layout' => 'Learning categories',
                    'homepage_featured_layout' => 'Featured courses',
                    'homepage_latest_layout' => 'Latest courses',
                    'homepage_live_classes_layout' => 'Upcoming live classes',
                    'homepage_exams_layout' => 'Exams & assessments',
                    'homepage_teachers_layout' => 'Teachers',
                    'homepage_reviews_layout' => 'Student reviews',
                    'homepage_ebooks_layout' => 'E-books & notes',
                ] as $key => $label)
                    <div><label class="mb-1 block text-sm font-semibold text-slate-700">{{ $label }}</label><select name="{{ $key }}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="carousel" @selected(($settings[$key] ?? 'carousel') === 'carousel')>Carousel</option><option value="grid" @selected(($settings[$key] ?? 'carousel') === 'grid')>Grid</option></select></div>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap items-end gap-5">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="homepage_carousel_autoplay" value="1" @checked(($settings['homepage_carousel_autoplay'] ?? '0') === '1')> Enable carousel autoplay</label>
                <div><label class="mb-1 block text-sm font-semibold text-slate-700">Autoplay interval (seconds)</label><input type="number" min="3" max="20" name="homepage_carousel_speed" value="{{ old('homepage_carousel_speed', $settings['homepage_carousel_speed'] ?? '6') }}" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            </div>
        </div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'homepage_learning_title' => ['Learning path heading', 'text', 'Choose your learning path'],
                'homepage_learning_subtitle' => ['Learning path description', 'text', 'Choose a course to improve your skills'],
                'homepage_testimonial_title' => ['Testimonial heading', 'text', 'What our students say'],
                'homepage_featured_title' => ['Featured courses heading', 'text', 'Featured courses'],
                'homepage_featured_subtitle' => ['Featured courses description', 'text', 'Hand-picked by our academic team.'],
                'homepage_online_batches_title' => ['Online batches heading', 'text', 'Online batches are ongoing!'],
                'homepage_online_batches_badge' => ['Online batches badge', 'text', 'Online Course'],
                'homepage_stats_title' => ['Statistics heading', 'text', 'See the stats!'],
                'homepage_stats_students_label' => ['Students statistic label', 'text', 'Students'],
                'homepage_stats_students_icon' => ['Students statistic icon (emoji)', 'text', '♙'],
                'homepage_stats_courses_label' => ['Courses statistic label', 'text', 'Courses'],
                'homepage_stats_courses_icon' => ['Courses statistic icon (emoji)', 'text', '▤'],
                'homepage_stats_teachers_label' => ['Teachers statistic label', 'text', 'Teachers'],
                'homepage_stats_teachers_icon' => ['Teachers statistic icon (emoji)', 'text', '♧'],
                'homepage_stats_lessons_label' => ['Lessons statistic label', 'text', 'Videos / Lessons'],
                'homepage_stats_lessons_icon' => ['Lessons statistic icon (emoji)', 'text', '▷'],
                'homepage_online_batches_subtitle' => ['Online batches description', 'text', 'Book your seat in an upcoming live learning batch.'],
                'homepage_latest_title' => ['Latest courses heading', 'text', 'Latest courses'],
                'homepage_latest_subtitle' => ['Latest courses description', 'text', 'Explore our newest learning opportunities.'],
                'homepage_live_classes_title' => ['Live classes heading', 'text', 'Upcoming live classes'],
                'homepage_live_classes_subtitle' => ['Live classes description', 'text', 'Join scheduled learning sessions with our instructors.'],
                'homepage_exams_title' => ['Exams heading', 'text', 'Exams & assessments'],
                'homepage_exams_subtitle' => ['Exams description', 'text', 'Prepare, practise and track your progress.'],
                'homepage_teachers_title' => ['Teachers heading', 'text', 'Meet our teachers'],
                'homepage_teachers_subtitle' => ['Teachers description', 'text', 'Learn with experienced instructors and practical guidance.'],
                'instructors_title' => ['Public instructors page heading', 'text', 'Our Expert Instructors'],
                'instructors_subtitle' => ['Public instructors page subtitle', 'text', 'Meet the experienced educators guiding your learning journey.'],
                'homepage_reviews_subtitle' => ['Student reviews description', 'text', 'Real feedback from our learning community.'],
                'homepage_ebooks_title' => ['E-books heading', 'text', 'E-books & notes'],
                'homepage_ebooks_subtitle' => ['E-books description', 'text', 'Study guides and resources for learning anywhere.'],
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
                'homepage_learning_bg' => ['Learning path background', '#f1f3ff'],
                'homepage_learning_text' => ['Learning path text', '#0f172a'],
                'homepage_testimonial_bg' => ['Testimonial background', '#111827'],
                'homepage_testimonial_text' => ['Testimonial text', '#ffffff'],
                'homepage_stats_bg' => ['Statistics background', '#062c30'],
                'homepage_stats_text' => ['Statistics text', '#ffffff'],
                'footer_bg' => ['Footer background', '#071b26'],
                'footer_text' => ['Footer text', '#cbd5e1'],
                'footer_heading' => ['Footer heading', '#ffffff'],
                'developer_bg' => ['Developer strip background', '#0f172a'],
                'developer_text_color' => ['Developer strip text', '#ffffff'],
            ] as $key => [$label, $default])
                @php($colorValue = $settings[$key] ?? $default)
                <div><label class="mb-1 block text-sm font-semibold text-slate-700">{{ $label }}</label><input type="color" name="{{ $key }}" value="{{ old($key, $colorValue) }}" class="h-11 w-full rounded-lg border border-slate-300 p-1"></div>
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
            @foreach (['brand_loader_bg' => ['Screen background', '#ffffff'], 'brand_loader_color' => ['Spinner / accent color', '#06b6d4'], 'brand_loader_text_color' => ['Message text color', '#0f172a']] as $key => [$label, $default])
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
\n
@push('scripts')
<script>
(() => {
    const list = document.getElementById('homepage-section-order-list');
    const field = document.getElementById('homepage-section-order-value');
    if (!list || !field) return;
    const sync = () => { field.value = JSON.stringify([...list.children].map(item => item.dataset.sectionKey)); };
    const items = new Map([...list.children].map(item => [item.dataset.sectionKey, item]));
    let saved = [];
    try { saved = JSON.parse(field.value || '[]'); } catch (_) { saved = []; }
    [...saved, ...items.keys()].forEach(key => {
        const item = items.get(key);
        if (item) { list.appendChild(item); items.delete(key); }
    });
    list.addEventListener('click', event => {
        const button = event.target.closest('button[data-move]');
        if (!button) return;
        const item = button.closest('[data-section-key]');
        if (button.dataset.move === 'up' && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
        } else if (button.dataset.move === 'down' && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
        }
        sync();
    });
    let dragged = null;
    list.addEventListener('dragstart', event => {
        dragged = event.target.closest('[data-section-key]');
        if (dragged) event.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragover', event => {
        if (!dragged) return;
        event.preventDefault();
        const target = event.target.closest('[data-section-key]');
        if (!target || target === dragged) return;
        const bounds = target.getBoundingClientRect();
        list.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? target : target.nextSibling);
    });
    list.addEventListener('drop', event => { event.preventDefault(); sync(); });
    list.addEventListener('dragend', () => { dragged = null; sync(); });
    sync();
})();
</script>
@endpush

@endsection
