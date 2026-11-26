<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('online_batch_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('online_batch_applications')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('admitted_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'batch_id']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_batch_enrollments');
    }
};
