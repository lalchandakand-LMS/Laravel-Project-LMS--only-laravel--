<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fee_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('type', 20)->default('course');
            $table->string('billing_cycle', 20)->default('once');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('due_day')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('student_fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 32)->unique();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('fee_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 180);
            $table->date('issued_at');
            $table->date('due_at')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'status', 'due_at']);
            $table->index(['branch_id', 'issued_at']);
        });

        Schema::create('student_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 32)->unique();
            $table->foreignId('invoice_id')->constrained('student_fee_invoices')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('applied_amount', 12, 2);
            $table->decimal('advance_amount', 12, 2)->default(0);
            $table->string('method', 20);
            $table->string('reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index(['student_id', 'received_at']);
        });

        Schema::create('student_fee_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('student_fee_payments')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note', 180)->nullable();
            $table->timestamps();
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_credits');
        Schema::dropIfExists('student_fee_payments');
        Schema::dropIfExists('student_fee_invoices');
        Schema::dropIfExists('fee_plans');
    }
};