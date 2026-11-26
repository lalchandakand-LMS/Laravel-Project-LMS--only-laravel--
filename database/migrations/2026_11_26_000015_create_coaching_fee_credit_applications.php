<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_fee_credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_id')->constrained('student_fee_credits')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('student_fee_invoices')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_credit_applications');
    }
};