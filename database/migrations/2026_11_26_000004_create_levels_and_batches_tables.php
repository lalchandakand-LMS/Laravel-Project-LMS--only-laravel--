<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('deleted_at')->nullable()->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('level_id')->nullable()->after('category_id')->constrained('levels')->nullOnDelete();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('levels')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('schedule_days')->nullable();
            $table->string('schedule_time')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 20)->default('upcoming');
            $table->timestamp('deleted_at')->nullable()->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'starts_at']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('course_id')->constrained('batches')->nullOnDelete();
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });
        Schema::dropIfExists('batches');
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('level_id');
        });
        Schema::dropIfExists('levels');
    }
};
