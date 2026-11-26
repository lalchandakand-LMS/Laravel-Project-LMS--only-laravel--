<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('batch_id')->constrained('branches')->nullOnDelete();
            $table->index(['branch_id', 'status']);
        });

        // Preserve the branch that originally owned an order before any student transfer.
        DB::table('orders')->whereNull('branch_id')->orderBy('id')->chunkById(250, function ($orders) {
            $userBranches = DB::table('users')
                ->whereIn('id', $orders->pluck('user_id')->unique())
                ->pluck('branch_id', 'id');

            foreach ($orders as $order) {
                $branchId = $userBranches[$order->user_id] ?? null;
                if ($branchId) {
                    DB::table('orders')->where('id', $order->id)->whereNull('branch_id')->update(['branch_id' => $branchId]);
                }
            }
        });

        // Snapshot historical course enrolments using the most specific ownership available.
        DB::table('enrollments')->whereNull('branch_id')->orderBy('id')->chunkById(250, function ($enrollments) {
            $orderBranches = DB::table('orders')
                ->whereIn('id', $enrollments->pluck('order_id')->filter()->unique())
                ->pluck('branch_id', 'id');
            $batchBranches = DB::table('batches')
                ->whereIn('id', $enrollments->pluck('batch_id')->filter()->unique())
                ->pluck('branch_id', 'id');
            $userBranches = DB::table('users')
                ->whereIn('id', $enrollments->pluck('user_id')->unique())
                ->pluck('branch_id', 'id');

            foreach ($enrollments as $enrollment) {
                $branchId = ($enrollment->order_id ? ($orderBranches[$enrollment->order_id] ?? null) : null)
                    ?? ($enrollment->batch_id ? ($batchBranches[$enrollment->batch_id] ?? null) : null)
                    ?? ($userBranches[$enrollment->user_id] ?? null);

                if ($branchId) {
                    DB::table('enrollments')->where('id', $enrollment->id)->whereNull('branch_id')->update(['branch_id' => $branchId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });

        // Historical order ownership is intentionally retained; it is operational ledger data.
    }
};
