<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('bkash_number', 40)->nullable();
            $table->string('nagad_number', 40)->nullable();
            $table->string('rocket_number', 40)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_branch', 120)->nullable();
            $table->string('bank_account_name', 120)->nullable();
            $table->string('bank_account_number', 80)->nullable();
            $table->text('payment_instructions')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['proof_path', 'verified_at', 'rejection_reason']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'bkash_number', 'nagad_number', 'rocket_number',
                'bank_name', 'bank_branch', 'bank_account_name',
                'bank_account_number', 'payment_instructions',
            ]);
        });
    }
};
