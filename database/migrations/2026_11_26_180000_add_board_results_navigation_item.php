<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $existing = MenuItem::where('location', 'header')
            ->where('url', '/#board-results')
            ->first();

        if ($existing) {
            $existing->update(['label' => 'Board Results', 'url' => '/board-result']);
            return;
        }

        MenuItem::firstOrCreate(
            ['location' => 'header', 'url' => '/board-result'],
            [
                'label' => 'Board Results',
                'icon' => '🏆',
                'sort_order' => 8,
                'is_enabled' => true,
                'show_desktop' => true,
                'show_mobile' => true,
                'open_new_tab' => false,
            ]
        );
    }

    public function down(): void
    {
        MenuItem::where('location', 'header')->where('url', '/board-result')->update(['label' => 'Board Result', 'url' => '/#board-results']);
    }
};
