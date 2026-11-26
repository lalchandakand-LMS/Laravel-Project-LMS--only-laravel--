<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        MenuItem::where('location', 'header')
            ->where('label', 'Instructors')
            ->where('url', '/#teachers')
            ->update(['url' => '/instructors']);
    }

    public function down(): void
    {
        MenuItem::where('location', 'header')
            ->where('label', 'Instructors')
            ->where('url', '/instructors')
            ->update(['url' => '/#teachers']);
    }
};
