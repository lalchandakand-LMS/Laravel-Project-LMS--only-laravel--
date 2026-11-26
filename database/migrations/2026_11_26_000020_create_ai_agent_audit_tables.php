<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('agent_type', 30);
            $table->longText('prompt');
            $table->longText('response')->nullable();
            $table->string('status', 20)->default('processing');
            $table->string('provider', 40)->default('gemini');
            $table->string('model', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['agent_type', 'status']);
        });

        Schema::create('ai_action_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_run_id')->constrained('ai_agent_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 60);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('result')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_proposals');
        Schema::dropIfExists('ai_agent_runs');
    }
};
