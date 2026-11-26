<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        MenuItem::firstOrCreate(
            ['location' => 'header', 'url' => '/board-result'],
            [
                'label' => 'Board Results',
                'icon' => '🏆',
                'sort_order' => 60,
                'is_enabled' => true,
                'show_desktop' => true,
                'show_mobile' => true,
                'open_new_tab' => false,
            ]
        );
    }

    public function down(): void
    {
        MenuItem::where('location', 'header')->where('url', '/board-result')->delete();
    }
};
