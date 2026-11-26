<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coaching_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coaching_admission_id')->unique()->constrained('coaching_admissions')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('coaching_batch_id')->constrained('coaching_batches')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('admitted_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
            $table->index(['coaching_batch_id', 'status']);
        });

        Schema::create('online_batch_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('student_note')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'batch_id']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_batch_applications');
        Schema::dropIfExists('coaching_enrollments');
    }
};
