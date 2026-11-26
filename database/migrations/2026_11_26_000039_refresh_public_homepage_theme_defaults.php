<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Start the refreshed homepage with the four requested core sections.
        // Admins can re-enable any optional section from Site Settings.
        DB::table('settings')
            ->whereIn('key', [
                'homepage_show_featured',
                'homepage_show_latest',
                'homepage_show_live_classes',
                'homepage_show_exams',
                'homepage_show_teachers',
                'homepage_show_board_results',
                'homepage_show_ebooks',
                'homepage_show_admission',
            ])
            ->update(['value' => '0']);

        $defaults = [
            'site_primary_color' => ['#ea580c', '#06b6d4'],
            'site_secondary_color' => ['#9a3412', '#0d9488'],
            'brand_loader_color' => ['#ea580c', '#06b6d4'],
            'homepage_learning_bg' => ['#ffffff', '#f1f3ff'],
            'homepage_testimonial_bg' => ['#f1f5f9', '#111827'],
            'homepage_testimonial_text' => ['#0f172a', '#ffffff'],
            'homepage_stats_bg' => ['#ea580c', '#062c30'],
            'footer_bg' => ['#ffffff', '#071b26'],
            'footer_text' => ['#475569', '#cbd5e1'],
            'footer_heading' => ['#0f172a', '#ffffff'],
        ];

        foreach ($defaults as $key => [$oldValue, $newValue]) {
            DB::table('settings')
                ->where('key', $key)
                ->where('value', $oldValue)
                ->update(['value' => $newValue]);
        }
    }

    public function down(): void
    {
        // Deliberately do not restore the old public design or optional sections.
        // These are user-facing settings and may have been customized after deployment.
    }
};
