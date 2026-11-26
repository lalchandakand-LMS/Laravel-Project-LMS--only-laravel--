<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('payments')
            ->whereNotNull('transaction_id')
            ->select('transaction_id')
            ->groupBy('transaction_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->pluck('transaction_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add the unique payment transaction reference index: duplicate transaction IDs already exist. Review and reconcile duplicate payment records before retrying this migration.'
            );
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('transaction_id', 'payments_transaction_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_transaction_id_unique');
        });
    }
};
