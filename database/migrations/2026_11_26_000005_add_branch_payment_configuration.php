<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->json('payment_methods')->nullable()->after('is_active');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('user_id')->constrained('branches')->nullOnDelete();
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('payment_methods');
        });
    }
};
