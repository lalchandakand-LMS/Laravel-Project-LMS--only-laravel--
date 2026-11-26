<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('payment_adjustments')
            ->whereNotNull('reference')
            ->select('reference')
            ->groupBy('reference')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->pluck('reference');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add the unique refund reference index: duplicate refund references already exist. Review and reconcile duplicate refund records before retrying this migration.'
            );
        }

        Schema::table('payment_adjustments', function (Blueprint $table) {
            $table->unique('reference', 'payment_adjustments_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_adjustments', function (Blueprint $table) {
            $table->dropUnique('payment_adjustments_reference_unique');
        });
    }
};
