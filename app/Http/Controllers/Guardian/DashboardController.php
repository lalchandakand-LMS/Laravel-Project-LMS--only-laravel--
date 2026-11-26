<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\CoachingEnrollment;
use App\Models\StudentFeeInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $guardian = auth()->user();
        $children = $guardian->children()->with('branch')->get();
        $childIds = $children->modelKeys();

        $verifiedPayments = DB::table('payments')
            ->select('order_id')
            ->where('status', 'verified')
            ->selectRaw('SUM(amount) as received_total')
            ->groupBy('order_id');
        $orderAdjustments = DB::table('payment_adjustments')
            ->select('order_id')
            ->selectRaw("SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END) as refund_total")
            ->selectRaw("SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as credit_total")
            ->groupBy('order_id');
        $orderDues = DB::table('orders')
            ->leftJoinSub($verifiedPayments, 'verified_payments', fn ($join) => $join->on('verified_payments.order_id', '=', 'orders.id'))
            ->leftJoinSub($orderAdjustments, 'order_adjustments', fn ($join) => $join->on('order_adjustments.order_id', '=', 'orders.id'))
            ->whereIn('orders.user_id', $childIds)
            ->whereIn('orders.status', ['pending', 'partially_paid'])
            ->select('orders.user_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.total - COALESCE(verified_payments.received_total, 0) + COALESCE(order_adjustments.refund_total, 0) - COALESCE(order_adjustments.credit_total, 0) > 0 THEN orders.total - COALESCE(verified_payments.received_total, 0) + COALESCE(order_adjustments.refund_total, 0) - COALESCE(order_adjustments.credit_total, 0) ELSE 0 END), 0) as due')
            ->groupBy('orders.user_id')
            ->get()
            ->keyBy('user_id');

        $summary = $children->map(function (User $child) use ($orderDues) {
            return [
                'child' => $child,
                'courses' => $child->enrollments()->count(),
                'coaching_batches' => CoachingEnrollment::query()
                    ->where('student_id', $child->id)
                    ->where('status', 'active')
                    ->whereNull('ended_at')
                    ->whereHas('course', fn ($course) => $course->where('is_published', true))
                    ->whereHas('admission', function ($admission) use ($child) {
                        $admission->where('status', 'admitted')
                            ->where(function ($branch) use ($child) {
                                $branch->whereNull('branch_id');
                                if ($child->branch_id !== null) {
                                    $branch->orWhere('branch_id', $child->branch_id);
                                }
                            });
                    })
                    ->whereHas('batch', function ($batch) use ($child) {
                        $batch->where('status', '!=', 'cancelled')
                            ->where(function ($branch) use ($child) {
                                $branch->whereNull('branch_id');
                                if ($child->branch_id !== null) {
                                    $branch->orWhere('branch_id', $child->branch_id);
                                }
                            });
                    })
                    ->count(),
                'coaching_due' => StudentFeeInvoice::with(['payments', 'creditApplications'])
                    ->where('student_id', $child->id)
                    ->whereIn('status', ['unpaid', 'partial'])
                    ->get()
                    ->sum(fn ($invoice) => $invoice->dueAmount()),
                'progress' => $child->overallProgress(),
                'avg_score' => round((float) $child->examAttempts()->avg('score'), 1),
                'exams' => $child->examAttempts()->count(),
                'certificates' => $child->certificates()->count(),
                'due' => (float) ($orderDues->get($child->id)->due ?? 0),
            ];
        });

        return view('guardian.dashboard', [
            'summary' => $summary,
            'orders' => Order::whereIn('user_id', $children->pluck('id'))
                ->with(['user', 'items'])->latest()->take(8)->get(),
        ]);
    }

    /** Detailed report card for one linked child. */
    public function child(User $child)
    {
        $guardian = auth()->user();
        $linked = $guardian->children()->whereKey($child->id)->exists();
        abort_unless($linked || $guardian->isAdmin(), 403, 'This student is not linked to your account.');

        return view('guardian.child', [
            'child' => $child->load('branch'),
            'enrollments' => $child->enrollments()->with('course')->get(),
            'attempts' => $child->examAttempts()->with('exam.course')->latest()->take(15)->get(),
            'certificates' => $child->certificates()->with('course')->get(),
            'orders' => $child->orders()->with('items')->latest()->take(10)->get(),
            'progress' => $child->overallProgress(),
        ]);
    }
}
