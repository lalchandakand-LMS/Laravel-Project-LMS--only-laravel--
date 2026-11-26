<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('type', 20)->default('percent');
            $table->decimal('value', 10, 2);
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('flash_sale_courses', function (Blueprint $table) {
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->primary(['flash_sale_id', 'course_id']);
        });

        Schema::create('flash_sale_categories', function (Blueprint $table) {
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['flash_sale_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_categories');
        Schema::dropIfExists('flash_sale_courses');
        Schema::dropIfExists('flash_sales');
    }
};
