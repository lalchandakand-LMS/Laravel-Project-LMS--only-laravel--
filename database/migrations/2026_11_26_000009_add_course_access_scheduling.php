<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('access_duration_days')->nullable();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->timestamp('available_at')->nullable()->index();
            $table->foreignId('prerequisite_lesson_id')->nullable()
                ->constrained('lessons')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prerequisite_lesson_id');
            $table->dropIndex(['available_at']);
            $table->dropColumn('available_at');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('access_duration_days');
        });
    }
};
