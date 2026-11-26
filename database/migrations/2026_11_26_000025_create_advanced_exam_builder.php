<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedInteger('random_question_count')->default(0)->after('shuffle_questions');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->timestamp('manual_reviewed_at')->nullable()->after('submitted_at');
            $table->text('manual_feedback')->nullable()->after('manual_reviewed_at');
            $table->decimal('max_score', 8, 2)->nullable()->after('score');
            $table->decimal('pass_mark_target', 8, 2)->nullable()->after('max_score');
            $table->unsignedInteger('duration_minutes')->nullable()->after('pass_mark_target');
            $table->decimal('negative_marking', 4, 2)->nullable()->after('duration_minutes');
        });

        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->nullable()->index();
            $table->string('difficulty', 30)->default('medium')->index();
            $table->string('type', 20)->default('mcq');
            $table->longText('question');
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('correct_option_index')->nullable();
            $table->decimal('marks', 6, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->index(['type', 'difficulty']);
        });

        Schema::create('exam_attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['exam_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_questions');
        Schema::dropIfExists('question_bank_items');
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['manual_reviewed_at', 'manual_feedback', 'max_score', 'pass_mark_target', 'duration_minutes', 'negative_marking']);
        });
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('random_question_count');
        });
    }
};
