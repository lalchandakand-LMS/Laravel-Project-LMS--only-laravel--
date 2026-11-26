<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coaching_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('levels')->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->decimal('admission_fee', 12, 2)->default(0);
            $table->decimal('monthly_fee', 12, 2)->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('schedule_days')->nullable();
            $table->string('schedule_time')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 20)->default('upcoming');
            $table->timestamps();
            $table->index(['branch_id', 'status', 'starts_at']);
        });

        Schema::create('coaching_admissions', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 32)->unique();
            $table->string('student_name', 160);
            $table->string('student_email')->index();
            $table->string('student_phone', 30);
            $table->string('guardian_name', 160)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('guardian_email')->nullable();
            $table->boolean('student_sms_consent')->default(false);
            $table->boolean('guardian_sms_consent')->default(false);
            $table->foreignId('coaching_batch_id')->constrained('coaching_batches')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->text('applicant_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('admitted_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_admissions');
        Schema::dropIfExists('coaching_batches');
    }
};
