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
        // Keep batch_id nullable because course-wide attendance sessions may exist.
    }
};
