<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_fee_invoices', function (Blueprint $table) {
            $table->foreignId('coaching_admission_id')->after('student_id')->constrained('coaching_admissions')->restrictOnDelete();
            $table->index(['coaching_admission_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('student_fee_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coaching_admission_id');
        });
    }
};