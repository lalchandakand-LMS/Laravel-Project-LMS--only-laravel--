<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_status', 30)->nullable()->index();
            $table->string('shipping_carrier', 100)->nullable();
            $table->string('tracking_number', 120)->nullable()->index();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipping_status']);
            $table->dropIndex(['tracking_number']);
            $table->dropColumn(['shipping_status', 'shipping_carrier', 'tracking_number', 'shipped_at', 'delivered_at']);
        });
    }
};
