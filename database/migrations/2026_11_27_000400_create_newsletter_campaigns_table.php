<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 180);
            $table->string('title', 180);
            $table->longText('body');
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_campaign_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_subscriber_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['newsletter_campaign_id', 'newsletter_subscriber_id'], 'newsletter_campaign_subscriber_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_deliveries');
        Schema::dropIfExists('newsletter_campaigns');
    }
};
