<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->string('subject', 120)->nullable()->after('professional_bio');
            $table->string('panel_group', 120)->nullable()->index()->after('subject');
            $table->string('education', 255)->nullable()->after('panel_group');
            $table->string('youtube_url', 2048)->nullable()->after('education');
            $table->string('facebook_url', 2048)->nullable()->after('youtube_url');
            $table->unsignedInteger('sort_order')->default(0)->index()->after('facebook_url');
        });

        $now = now();
        foreach ([
            'instructors_title' => 'Our Expert Instructors',
            'instructors_subtitle' => 'Meet the experienced educators guiding your learning journey.',
        ] as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Upgrade only the untouched default menu item; preserve admin-customized links.
        DB::table('menu_items')->where('label', 'Instructors')->where('url', '/#teachers')
            ->update(['url' => '/instructors', 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropIndex(['panel_group']);
            $table->dropIndex(['sort_order']);
            $table->dropColumn(['subject', 'panel_group', 'education', 'youtube_url', 'facebook_url', 'sort_order']);
        });
        DB::table('settings')->whereIn('key', ['instructors_title', 'instructors_subtitle'])->delete();
        DB::table('menu_items')->where('label', 'Instructors')->where('url', '/instructors')
            ->update(['url' => '/#teachers']);
    }
};
