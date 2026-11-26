<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_popups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->string('target_audience', 20)->default('all')->index();
            $table->string('display_on', 24)->default('all_public_pages');
            $table->string('frequency', 24)->default('once_per_session');
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'starts_at', 'ends_at', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_popups');
    }
};
