<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('label', 80);
            $table->string('url', 2048);
            $table->string('location', 20)->default('header')->index();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('icon', 60)->nullable();
            $table->boolean('open_new_tab')->default(false);
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('show_desktop')->default(true);
            $table->boolean('show_mobile')->default(true);
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['Home', '/', 1],
            ['Courses', '/courses', 2],
            ['Learn', '/my/my-courses', 3],
            ['Instructors', '/#teachers', 4],
            ['Exams', '/my/exams', 5],
            ['Products', '/courses', 6],
            ['E-Books', '/ebooks', 7],
            ['Board Result', '/#board-results', 8],
            ['Profile', '/my/profile', 9],
        ];

        foreach ($defaults as [$label, $url, $order]) {
            DB::table('menu_items')->insert([
                'label' => $label,
                'url' => $url,
                'location' => 'header',
                'sort_order' => $order,
                'is_enabled' => true,
                'show_desktop' => true,
                'show_mobile' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
