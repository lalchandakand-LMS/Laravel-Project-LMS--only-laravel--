<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('live_class_id')->nullable()->after('batch_id')
                ->constrained('live_classes')->nullOnDelete();
            $table->unique('live_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropUnique(['live_class_id']);
            $table->dropConstrainedForeignId('live_class_id');
        });
    }
};
