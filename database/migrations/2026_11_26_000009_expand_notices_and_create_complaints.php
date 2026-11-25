<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('priority', 20)->default('general')->index();
            $table->string('audience_role', 30)->nullable()->index();
            $table->foreignId('batch_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_pinned')->default(false)->index();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40)->index();
            $table->string('subject', 180);
            $table->longText('body');
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('submitted')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('complaint_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->index(['complaint_id', 'created_at']);
        });

        Schema::create('complaint_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->text('description');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->index(['complaint_id', 'created_at']);
        });

        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reply_id')->nullable()->constrained('complaint_replies')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_attachments');
        Schema::dropIfExists('complaint_events');
        Schema::dropIfExists('complaint_replies');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('announcement_reads');
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn(['priority', 'audience_role', 'publish_at', 'expires_at', 'is_pinned']);
        });
    }
};
