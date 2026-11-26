<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $item = MenuItem::where('location', 'header')
            ->where('label', 'Products')
            ->first();

        if ($item) {
            $item->update(['url' => '/products']);
            return;
        }

        MenuItem::create([
            'label' => 'Products',
            'url' => '/products',
            'location' => 'header',
            'sort_order' => 6,
            'icon' => '🛍️',
            'is_enabled' => true,
            'show_desktop' => true,
            'show_mobile' => true,
        ]);
    }

    public function down(): void
    {
        MenuItem::where('location', 'header')
            ->where('label', 'Products')
            ->where('url', '/products')
            ->update(['url' => '/courses']);
    }
};
