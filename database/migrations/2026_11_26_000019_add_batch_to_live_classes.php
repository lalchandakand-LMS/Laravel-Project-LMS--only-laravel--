<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('live_classes', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('course_id')
                ->constrained('batches')->nullOnDelete();
            $table->index(['batch_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('live_classes', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'is_published']);
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
