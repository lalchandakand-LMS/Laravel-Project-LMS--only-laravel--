<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->foreignId('coaching_batch_id')->nullable()->after('batch_id')->constrained('coaching_batches')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->change();
        });
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->index(['coaching_batch_id', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex(['coaching_batch_id', 'status', 'starts_at']);
            $table->dropConstrainedForeignId('coaching_batch_id');
            $table->foreignId('batch_id')->nullable(false)->change();
            $table->foreign('batch_id')->references('id')->on('batches')->restrictOnDelete();
        });
    }
};
