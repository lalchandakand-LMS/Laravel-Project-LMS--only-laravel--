<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only migrate the former built-in order. Preserve any order explicitly
        // customized by an administrator.
        $legacyDefault = json_encode([
            'hero', 'categories', 'featured', 'online_batches', 'stats',
            'latest', 'live_classes', 'exams', 'teachers', 'board_results',
            'reviews', 'ebooks', 'admission',
        ]);

        $newDefault = json_encode([
            'hero', 'online_batches', 'stats', 'categories', 'featured',
            'latest', 'live_classes', 'exams', 'teachers', 'board_results',
            'reviews', 'ebooks', 'admission',
        ]);

        DB::table('settings')
            ->where('key', 'homepage_section_order')
            ->where('value', $legacyDefault)
            ->update(['value' => $newDefault, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Keep the newer order: an administrator may have customized it
        // after this migration ran, so reverting it would overwrite settings.
    }
};
