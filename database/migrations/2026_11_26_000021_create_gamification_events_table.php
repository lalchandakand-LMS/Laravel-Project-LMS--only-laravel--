<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gamification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('event_key', 120);
            $table->unsignedInteger('points');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'event_key']);
            $table->index(['user_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_events');
    }
};
