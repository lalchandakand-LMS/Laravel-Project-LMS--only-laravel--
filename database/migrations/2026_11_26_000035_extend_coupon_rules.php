<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->decimal('max_discount', 10, 2)->nullable()->after('min_amount');
            $table->foreignId('branch_id')->nullable()->after('max_discount')->constrained()->nullOnDelete();
            $table->json('course_ids')->nullable()->after('branch_id');
            $table->json('category_ids')->nullable()->after('course_ids');
            $table->json('ebook_ids')->nullable()->after('category_ids');
            $table->unsignedInteger('per_user_limit')->default(0)->after('usage_limit');
            $table->boolean('first_order_only')->default(false)->after('per_user_limit');
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'is_active']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['max_discount', 'course_ids', 'category_ids', 'ebook_ids', 'per_user_limit', 'first_order_only']);
        });
    }
};
