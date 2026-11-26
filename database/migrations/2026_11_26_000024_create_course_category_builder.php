<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 300)->nullable();
        });

        Schema::create('course_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'category_id']);
        });

        DB::table('courses')->whereNotNull('category_id')->orderBy('id')->each(function ($course) {
            DB::table('course_category')->insertOrIgnore([
                'course_id' => $course->id,
                'category_id' => $course->category_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_category');
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
