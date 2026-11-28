<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    private const HOMEPAGE_SECTION_KEYS = ['hero', 'categories', 'featured', 'online_batches', 'stats', 'latest', 'live_classes', 'exams', 'teachers', 'board_results', 'reviews', 'ebooks', 'admission'];
    public const KEYS = [
        'site_name', 'site_tagline', 'admin_logo', 'admin_theme_preset', 'admin_card_radius', 'admin_brand_title', 'admin_brand_subtitle', 'admin_sidebar_promo_title', 'admin_sidebar_promo_body', 'admin_sidebar_promo_button', 'admin_sidebar_promo_url', 'site_email', 'site_phone', 'site_address', 'company_license', 'site_primary_color', 'site_secondary_color',
        'facebook', 'youtube', 'instagram', 'bkash_number', 'nagad_number',
        'currency', 'certificate_signature', 'about_text',
        'dashboard_banner_title', 'dashboard_banner_subtitle',
        'dashboard_banner_button_text', 'dashboard_banner_button_url',
        'dashboard_banner_enabled', 'dashboard_primary_color', 'dashboard_secondary_color',
        'dashboard_widget_stats', 'dashboard_widget_chart', 'dashboard_widget_courses',
        'dashboard_widget_orders', 'dashboard_widget_enrollments', 'dashboard_widget_reviews',
        'dashboard_widget_student_stats', 'dashboard_widget_student_courses', 'dashboard_widget_student_exams', 'dashboard_widget_student_activity',
        'dashboard_widget_teacher_stats', 'dashboard_widget_teacher_trend', 'dashboard_widget_teacher_upcoming', 'dashboard_widget_teacher_courses', 'dashboard_widget_teacher_students',
        'dashboard_widget_branch_stats', 'dashboard_widget_branch_collection', 'dashboard_widget_branch_courses', 'dashboard_widget_branch_students', 'dashboard_widget_branch_orders',
        'dashboard_widget_guardian_children', 'dashboard_widget_guardian_orders',
        'homepage_theme_mode', 'homepage_animation_style', 'homepage_animation_speed', 'homepage_section_order', 'admin_sidebar_promo_enabled', 'homepage_show_hero', 'homepage_show_categories', 'homepage_show_featured', 'homepage_show_online_batches', 'homepage_show_stats', 'homepage_show_latest', 'homepage_show_reviews', 'homepage_show_ebooks', 'homepage_show_live_classes', 'homepage_show_exams', 'homepage_show_teachers', 'homepage_show_board_results', 'homepage_show_admission', 'homepage_learning_layout', 'homepage_featured_layout', 'homepage_latest_layout', 'homepage_live_classes_layout', 'homepage_exams_layout', 'homepage_teachers_layout', 'homepage_reviews_layout', 'homepage_ebooks_layout', 'homepage_carousel_autoplay', 'homepage_carousel_speed',
        'homepage_board_results_title', 'homepage_board_results_subtitle', 'homepage_board_results_body', 'homepage_board_results_year',
        'homepage_admission_title', 'homepage_admission_subtitle', 'homepage_admission_button_text', 'homepage_admission_button_url',
        'homepage_learning_title', 'homepage_learning_subtitle', 'homepage_testimonial_title', 'homepage_featured_title', 'homepage_featured_subtitle', 'homepage_online_batches_title', 'homepage_online_batches_subtitle', 'homepage_online_batches_badge', 'homepage_stats_title', 'homepage_stats_students_label', 'homepage_stats_students_icon', 'homepage_stats_courses_label', 'homepage_stats_courses_icon', 'homepage_stats_teachers_label', 'homepage_stats_teachers_icon', 'homepage_stats_lessons_label', 'homepage_stats_lessons_icon', 'homepage_latest_title', 'homepage_latest_subtitle', 'homepage_live_classes_title', 'homepage_live_classes_subtitle', 'homepage_exams_title', 'homepage_exams_subtitle', 'homepage_teachers_title', 'homepage_teachers_subtitle', 'instructors_title', 'instructors_subtitle', 'homepage_reviews_subtitle', 'homepage_ebooks_title', 'homepage_ebooks_subtitle',
        'homepage_learning_bg', 'homepage_learning_text', 'homepage_testimonial_bg', 'homepage_testimonial_text',
        'homepage_stats_bg', 'homepage_stats_text', 'footer_bg', 'footer_text', 'footer_heading', 'footer_copyright',
        'developer_name', 'developer_url', 'developer_text', 'developer_bg', 'developer_text_color', 'developer_show',
        'brand_loader_enabled', 'brand_loader_message', 'brand_loader_style', 'brand_loader_min_time', 'brand_loader_bg', 'brand_loader_color', 'brand_loader_text_color',
    ];

    /**
     * Public CTA links are restricted to same-site root paths, anchors, and
     * absolute HTTP(S) URLs to prevent javascript:/data: navigation injection.
     */
    private function safeNavigationTarget(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            $url = trim((string) $value);

            if (str_starts_with($url, '#')
                || (str_starts_with($url, '/') && ! str_starts_with($url, '//')
                    && ! str_contains($url, '\\')
                    && preg_match('/[\x00-\x20]/', $url) !== 1)) {
                return;
            }

            $parts = parse_url($url);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $safeExternal = is_array($parts)
                && in_array($scheme, ['http', 'https'], true)
                && filled($parts['host'] ?? null)
                && ! isset($parts['user'])
                && ! isset($parts['pass']);

            if (! $safeExternal) {
                $fail('Use a same-site path, anchor, or http/https URL.');
            }
        };
    }

    public function index()
    {
        return view('admin.settings', ['settings' => Setting::all_()]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name' => ['sometimes', 'required', 'string', 'max:120'],
            'admin_theme_preset' => ['sometimes', 'required', 'in:navy,orange,teal,purple'],
            'admin_card_radius' => ['sometimes', 'required', 'integer', 'in:10,14,18,22'],
            'admin_brand_title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'admin_brand_subtitle' => ['sometimes', 'nullable', 'string', 'max:100'],
            'admin_sidebar_promo_title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'admin_sidebar_promo_body' => ['sometimes', 'nullable', 'string', 'max:240'],
            'admin_sidebar_promo_button' => ['sometimes', 'nullable', 'string', 'max:40'],
            'admin_sidebar_promo_url' => ['sometimes', 'nullable', 'string', 'max:2048', $this->safeNavigationTarget()],
            'site_tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'company_license' => ['sometimes', 'nullable', 'string', 'max:120'],
            'site_primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'site_secondary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'facebook' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'youtube' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'instagram' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'admin_logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'logo_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'dashboard_banner' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'dashboard_banner_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'dashboard_banner_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'dashboard_banner_subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'dashboard_banner_button_text' => ['sometimes', 'nullable', 'string', 'max:60'],
            'dashboard_banner_button_url' => ['sometimes', 'nullable', 'string', 'max:2048', $this->safeNavigationTarget()],
            'dashboard_primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_secondary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_banner_enabled' => ['sometimes', 'boolean'],
            'homepage_theme_mode' => ['sometimes', 'required', 'in:light,dark'],
            'homepage_animation_style' => ['sometimes', 'required', 'in:none,fade,rise,slide,zoom'],
            'homepage_animation_speed' => ['sometimes', 'required', 'integer', 'min:200', 'max:1200'],
            'homepage_learning_layout' => ['sometimes', 'required', 'in:grid,carousel'],
            'homepage_featured_layout' => ['sometimes', 'required', 'in:grid,carousel'],
            'homepage_latest_layout' => ['sometimes', 'required', 'in:grid,carousel'],
            'homepage_live_classes_layout' => ['sometimes', 'required', 'in:grid,carousel'],
            'homepage_exams_layout' => ['sometimes', 'required', 'in:grid,carousel'],
            'homepage_teachers_layout' => ['sometimes', 'required', 'in:grid,carousel'],
            'homepage_reviews_layout' => ['sometimes', 'required', 'in:grid,carousel'],
            'homepage_ebooks_layout' => ['sometimes', 'required', 'in:grid,carousel'],
            'homepage_carousel_autoplay' => ['sometimes', 'boolean'],
            'homepage_carousel_speed' => ['sometimes', 'required', 'integer', 'min:3', 'max:20'],
            'homepage_section_order' => ['sometimes', 'required', 'json', function ($attribute, $value, $fail) {
                $order = json_decode($value, true);
                if (! is_array($order) || ! array_is_list($order) ||
                    count($order) !== count(self::HOMEPAGE_SECTION_KEYS) ||
                    count(array_filter($order, 'is_string')) !== count($order)) {
                    $fail('Choose each homepage section exactly once in the display order.');
                    return;
                }

                if (count(array_unique($order)) !== count($order) ||
                    array_diff($order, self::HOMEPAGE_SECTION_KEYS)) {
                    $fail('Choose each homepage section exactly once in the display order.');
                }
            }],
            'homepage_board_results_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'homepage_board_results_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'homepage_board_results_body' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'homepage_board_results_year' => ['sometimes', 'nullable', 'string', 'max:20'],
            'homepage_admission_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'homepage_admission_subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'homepage_admission_button_text' => ['sometimes', 'nullable', 'string', 'max:60'],
            'homepage_admission_button_url' => ['sometimes', 'nullable', 'string', 'max:2048', $this->safeNavigationTarget()],
            'homepage_learning_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'homepage_learning_subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'homepage_testimonial_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'homepage_featured_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'homepage_featured_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'homepage_online_batches_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'homepage_online_batches_badge' => ['sometimes', 'nullable', 'string', 'max:40'],
            'homepage_stats_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'homepage_stats_students_label' => ['sometimes', 'nullable', 'string', 'max:40'],
            'homepage_stats_students_icon' => ['sometimes', 'nullable', 'string', 'max:8'],
            'homepage_stats_courses_label' => ['sometimes', 'nullable', 'string', 'max:40'],
            'homepage_stats_courses_icon' => ['sometimes', 'nullable', 'string', 'max:8'],
            'homepage_stats_teachers_label' => ['sometimes', 'nullable', 'string', 'max:40'],
            'homepage_stats_teachers_icon' => ['sometimes', 'nullable', 'string', 'max:8'],
            'homepage_stats_lessons_label' => ['sometimes', 'nullable', 'string', 'max:40'],
            'homepage_stats_lessons_icon' => ['sometimes', 'nullable', 'string', 'max:8'],
            'homepage_online_batches_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'homepage_latest_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'homepage_latest_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'homepage_live_classes_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'homepage_live_classes_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'homepage_exams_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'homepage_exams_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'homepage_teachers_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'homepage_teachers_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instructors_title' => ['sometimes', 'required', 'string', 'max:120'],
            'instructors_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'homepage_reviews_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'homepage_ebooks_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'homepage_ebooks_subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'homepage_learning_bg' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'homepage_learning_text' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'homepage_testimonial_bg' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'homepage_testimonial_text' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'homepage_stats_bg' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'homepage_stats_text' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_bg' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_text' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_heading' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_copyright' => ['sometimes', 'nullable', 'string', 'max:255'],
            'developer_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'developer_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'developer_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'developer_bg' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'developer_text_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'developer_show' => ['sometimes', 'boolean'],
            'brand_loader_enabled' => ['sometimes', 'boolean'],
            'brand_loader_message' => ['sometimes', 'nullable', 'string', 'max:120'],
            'brand_loader_style' => ['sometimes', 'required', 'in:ring,dots,pulse'],
            'brand_loader_min_time' => ['sometimes', 'required', 'integer', 'min:0', 'max:2000'],
            'brand_loader_bg' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_loader_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_loader_text_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        foreach (self::KEYS as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        foreach (['dashboard_widget_stats', 'dashboard_widget_chart', 'dashboard_widget_courses', 'dashboard_widget_orders', 'dashboard_widget_enrollments', 'dashboard_widget_reviews',
            'dashboard_widget_student_stats', 'dashboard_widget_student_courses', 'dashboard_widget_student_exams', 'dashboard_widget_student_activity',
            'dashboard_widget_teacher_stats', 'dashboard_widget_teacher_trend', 'dashboard_widget_teacher_upcoming', 'dashboard_widget_teacher_courses', 'dashboard_widget_teacher_students',
            'dashboard_widget_branch_stats', 'dashboard_widget_branch_collection', 'dashboard_widget_branch_courses', 'dashboard_widget_branch_students', 'dashboard_widget_branch_orders',
            'dashboard_widget_guardian_children', 'dashboard_widget_guardian_orders',
            'homepage_show_hero', 'homepage_show_categories', 'homepage_show_featured', 'homepage_show_online_batches', 'homepage_show_stats', 'homepage_show_latest', 'homepage_show_reviews', 'homepage_show_ebooks', 'homepage_show_live_classes', 'homepage_show_exams', 'homepage_show_teachers', 'homepage_show_board_results', 'homepage_show_admission', 'homepage_carousel_autoplay', 'developer_show', 'brand_loader_enabled'] as $key) {
            Setting::set($key, $request->boolean($key) ? '1' : '0');
        }

        foreach (['logo' => 'brand', 'admin_logo' => 'brand/admin', 'dashboard_banner' => 'brand/dashboard'] as $field => $directory) {
            if ($request->hasFile($field)) {
                $oldPath = Setting::get($field);
                $newPath = $request->file($field)->store($directory, 'public');

                if (! is_string($newPath) || $newPath === '') {
                    throw ValidationException::withMessages([
                        $field => 'The uploaded brand media could not be stored. The existing media has been kept.',
                    ]);
                }

                try {
                    MediaAsset::create([
                        'title' => match ($field) {
                            'logo' => 'Site logo',
                            'admin_logo' => 'Admin logo',
                            default => 'Dashboard banner',
                        },
                        'source_type' => 'upload',
                        'disk' => 'public',
                        'path' => $newPath,
                        'original_name' => $request->file($field)->getClientOriginalName(),
                        'mime_type' => $request->file($field)->getMimeType(),
                        'size_bytes' => $request->file($field)->getSize(),
                        'uploaded_by' => $request->user()->id,
                    ]);
                } catch (\Throwable $exception) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($newPath);
                    throw $exception;
                }

                Setting::set($field, $newPath);
                // Keep replaced files available in the Media Library for reuse.
                // The library's reference-aware delete action controls actual removal.
            }
        }

        foreach (['logo_url' => 'logo', 'dashboard_banner_url' => 'dashboard_banner'] as $field => $key) {
            if ($request->filled($field) && ! $request->hasFile($key)) {
                $oldPath = Setting::get($key);
                Setting::set($key, $request->input($field));
                if ($oldPath && ! (str_starts_with(strtolower($oldPath), 'http://') || str_starts_with(strtolower($oldPath), 'https://'))) {
                    if (! MediaAsset::query()->where('disk', 'public')->where('path', $oldPath)->exists()) {\n                    delete_stored_media('public', $oldPath);\n                }
                }
            }
        }

        foreach (['remove_logo' => 'logo', 'remove_admin_logo' => 'admin_logo', 'remove_dashboard_banner' => 'dashboard_banner'] as $flag => $key) {
            if ($request->boolean($flag)) {
                $oldPath = Setting::get($key);
                Setting::set($key, '');
                if ($oldPath && ! str_starts_with(strtolower($oldPath), 'http://') && ! str_starts_with(strtolower($oldPath), 'https://')) {
                    if (! MediaAsset::query()->where('disk', 'public')->where('path', $oldPath)->exists()) {\n                    delete_stored_media('public', $oldPath);\n                }
                }
            }
        }

        Setting::set('dashboard_banner_enabled', $request->boolean('dashboard_banner_enabled') ? '1' : '0');

        return back()->with('success', 'Settings saved.');
    }
}
