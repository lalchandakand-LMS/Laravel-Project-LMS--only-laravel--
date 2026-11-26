<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('student_fee_payments')
            ->whereNotNull('reference')
            ->select('reference')
            ->groupBy('reference')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->pluck('reference');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add the unique student-fee payment reference index: duplicate references already exist. Review and reconcile duplicate fee-payment records before retrying this migration.'
            );
        }

        Schema::table('student_fee_payments', function (Blueprint $table) {
            $table->unique('reference', 'student_fee_payments_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_fee_payments', function (Blueprint $table) {
            $table->dropUnique('student_fee_payments_reference_unique');
        });
    }
};
