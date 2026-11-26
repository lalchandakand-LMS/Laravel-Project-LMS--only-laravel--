<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->longText('description')->nullable();
            $table->longText('instructions')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->decimal('total_marks', 8, 2)->default(100);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['course_id', 'is_published']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->longText('answer_text')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->decimal('score', 8, 2)->nullable();
            $table->longText('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
    }
};
