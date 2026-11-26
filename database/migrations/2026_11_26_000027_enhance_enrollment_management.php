<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('enrollments', 'admin_note')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->text('admin_note')->nullable();
            });
        }

        Schema::create('enrollment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['enrollment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_histories');
        if (Schema::hasColumn('enrollments', 'admin_note')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropColumn('admin_note');
            });
        }
    }
};
