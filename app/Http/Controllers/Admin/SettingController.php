<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public const KEYS = [
        'site_name', 'site_tagline', 'site_email', 'site_phone', 'site_address',
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
        'homepage_show_live_classes', 'homepage_show_exams', 'homepage_show_teachers', 'homepage_show_board_results', 'homepage_show_admission',
        'homepage_board_results_title', 'homepage_board_results_subtitle', 'homepage_board_results_body', 'homepage_board_results_year',
        'homepage_admission_title', 'homepage_admission_subtitle', 'homepage_admission_button_text', 'homepage_admission_button_url',
        'homepage_learning_title', 'homepage_learning_subtitle', 'homepage_testimonial_title',
        'homepage_learning_bg', 'homepage_learning_text', 'homepage_testimonial_bg', 'homepage_testimonial_text',
        'homepage_stats_bg', 'homepage_stats_text', 'footer_bg', 'footer_text', 'footer_heading', 'footer_copyright',
        'developer_name', 'developer_url', 'developer_text', 'developer_bg', 'developer_text_color', 'developer_show',
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
            'facebook' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'youtube' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'instagram' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'dashboard_banner' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'dashboard_banner_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'dashboard_banner_subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'dashboard_banner_button_text' => ['sometimes', 'nullable', 'string', 'max:60'],
            'dashboard_banner_button_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'dashboard_primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_secondary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_banner_enabled' => ['sometimes', 'boolean'],
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
            'homepage_show_live_classes', 'homepage_show_exams', 'homepage_show_teachers', 'homepage_show_board_results', 'homepage_show_admission', 'developer_show'] as $key) {
            Setting::set($key, $request->boolean($key) ? '1' : '0');
        }

        foreach (['logo' => 'brand', 'dashboard_banner' => 'brand/dashboard'] as $field => $directory) {
            if ($request->hasFile($field)) {
                $oldPath = Setting::get($field);
                $newPath = $request->file($field)->store($directory, 'public');
                if ($newPath) {
                    Setting::set($field, $newPath);
                    if ($oldPath && $oldPath !== $newPath) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            }
        }

        foreach (['remove_logo' => 'logo', 'remove_dashboard_banner' => 'dashboard_banner'] as $flag => $key) {
            if ($request->boolean($flag)) {
                $oldPath = Setting::get($key);
                Setting::set($key, '');
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }

        Setting::set('dashboard_banner_enabled', $request->boolean('dashboard_banner_enabled') ? '1' : '0');

        return back()->with('success', 'Settings saved.');
    }
}
