<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const HOMEPAGE_SECTION_KEYS = ['hero', 'categories', 'featured', 'online_batches', 'stats', 'latest', 'live_classes', 'exams', 'teachers', 'board_results', 'reviews', 'ebooks', 'admission'];
    public const KEYS = [
        'site_name', 'site_tagline', 'site_email', 'site_phone', 'site_address', 'site_primary_color', 'site_secondary_color',
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
        'homepage_section_order', 'homepage_show_hero', 'homepage_show_categories', 'homepage_show_featured', 'homepage_show_online_batches', 'homepage_show_stats', 'homepage_show_latest', 'homepage_show_reviews', 'homepage_show_ebooks', 'homepage_show_live_classes', 'homepage_show_exams', 'homepage_show_teachers', 'homepage_show_board_results', 'homepage_show_admission', 'homepage_learning_layout', 'homepage_featured_layout', 'homepage_latest_layout', 'homepage_live_classes_layout', 'homepage_exams_layout', 'homepage_teachers_layout', 'homepage_reviews_layout', 'homepage_ebooks_layout', 'homepage_carousel_autoplay', 'homepage_carousel_speed',
        'homepage_board_results_title', 'homepage_board_results_subtitle', 'homepage_board_results_body', 'homepage_board_results_year',
        'homepage_admission_title', 'homepage_admission_subtitle', 'homepage_admission_button_text', 'homepage_admission_button_url',
        'homepage_learning_title', 'homepage_learning_subtitle', 'homepage_testimonial_title',
        'homepage_learning_bg', 'homepage_learning_text', 'homepage_testimonial_bg', 'homepage_testimonial_text',
        'homepage_stats_bg', 'homepage_stats_text', 'footer_bg', 'footer_text', 'footer_heading', 'footer_copyright',
        'developer_name', 'developer_url', 'developer_text', 'developer_bg', 'developer_text_color', 'developer_show',
        'brand_loader_enabled', 'brand_loader_message', 'brand_loader_style', 'brand_loader_min_time', 'brand_loader_bg', 'brand_loader_color', 'brand_loader_text_color',
    ];

    public function index()
    {
        return view('admin.settings', ['settings' => Setting::all_()]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name' => ['sometimes', 'required', 'string', 'max:120'],
            'site_tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'site_primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'site_secondary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'facebook' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'youtube' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'instagram' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'logo_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'dashboard_banner' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'dashboard_banner_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'dashboard_banner_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'dashboard_banner_subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'dashboard_banner_button_text' => ['sometimes', 'nullable', 'string', 'max:60'],
            'dashboard_banner_button_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'dashboard_primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_secondary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_banner_enabled' => ['sometimes', 'boolean'],
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
                    count(array_unique($order)) !== count($order) ||
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
            'homepage_admission_button_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'homepage_learning_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'homepage_learning_subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'homepage_testimonial_title' => ['sometimes', 'nullable', 'string', 'max:180'],
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
            'developer_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
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

        foreach (['logo' => 'brand', 'dashboard_banner' => 'brand/dashboard'] as $field => $directory) {
            if ($request->hasFile($field)) {
                $oldPath = Setting::get($field);
                $newPath = $request->file($field)->store($directory, 'public');
                if ($newPath) {
                    Setting::set($field, $newPath);
                    if ($oldPath && $oldPath !== $newPath && ! str_starts_with(strtolower($oldPath), 'http://') && ! str_starts_with(strtolower($oldPath), 'https://')) {
                        delete_stored_media('public', $oldPath);
                    }
                }
            }
        }

        foreach (['logo_url' => 'logo', 'dashboard_banner_url' => 'dashboard_banner'] as $field => $key) {
            if ($request->filled($field) && ! $request->hasFile($key)) {
                $oldPath = Setting::get($key);
                Setting::set($key, $request->input($field));
                if ($oldPath && ! (str_starts_with(strtolower($oldPath), 'http://') || str_starts_with(strtolower($oldPath), 'https://'))) {
                    delete_stored_media('public', $oldPath);
                }
            }
        }

        foreach (['remove_logo' => 'logo', 'remove_dashboard_banner' => 'dashboard_banner'] as $flag => $key) {
            if ($request->boolean($flag)) {
                $oldPath = Setting::get($key);
                Setting::set($key, '');
                if ($oldPath && ! str_starts_with(strtolower($oldPath), 'http://') && ! str_starts_with(strtolower($oldPath), 'https://')) {
                    delete_stored_media('public', $oldPath);
                }
            }
        }

        Setting::set('dashboard_banner_enabled', $request->boolean('dashboard_banner_enabled') ? '1' : '0');

        return back()->with('success', 'Settings saved.');
    }
}
