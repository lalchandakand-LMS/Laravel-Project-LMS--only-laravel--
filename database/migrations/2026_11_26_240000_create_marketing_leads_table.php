<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('phone', 40)->nullable();
            $table->string('subject', 180)->nullable();
            $table->text('message');
            $table->string('source', 80)->default('website');
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 160)->nullable();
            $table->string('utm_term', 160)->nullable();
            $table->string('utm_content', 160)->nullable();
            $table->string('landing_page', 500)->nullable();
            $table->string('referrer_host', 255)->nullable();
            $table->string('status', 24)->default('new')->index();
            $table->text('admin_notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['utm_campaign', 'created_at']);
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
