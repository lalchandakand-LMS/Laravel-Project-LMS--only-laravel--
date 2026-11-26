<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_key', 64);
            $table->date('viewed_on');
            $table->timestamps();
            $table->unique(['course_id', 'visitor_key', 'viewed_on'], 'course_views_daily_unique');
            $table->index(['viewed_on', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_views');
    }
};
