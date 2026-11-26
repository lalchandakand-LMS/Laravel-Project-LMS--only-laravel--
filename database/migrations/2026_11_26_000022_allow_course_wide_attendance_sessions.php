<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::table('attendance_sessions', fn ($table) => null) === null) {
            // Keep batch_id nullable: course-wide attendance sessions may depend on it.
        }
    }
};
