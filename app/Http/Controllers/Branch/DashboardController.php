<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CoachingEnrollment;
use App\Models\Enrollment;
use App\Models\OnlineBatchEnrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Resolve the manager's assigned branch; never trust a submitted branch ID. */
    private function branch(): Branch
    {
        $user = auth()->user();

        $branch = $user->isBranchManager()
            ? Branch::forManager($user)
            : ($user->branch ?? Branch::where('manager_id', $user->id)->first());

        abort_unless($branch, 403, 'No branch has been assigned to your account yet. Please contact the admin.');

        if ($user->isBranchManager() && $user->branch_id && (int) $user->branch_id !== (int) $branch->id) {
            abort(403, 'Your branch assignment is inconsistent. Please contact the admin.');
        }

        return $branch;
    }

    public function index()
    {
        $branch = $this->branch();
        $studentCount = $branch->students()->count();

        $courseEnrollments = Enrollment::query()
            ->where(function ($query) use ($branch) {
                $query->where('branch_id', $branch->id)
                    ->orWhere(function ($legacy) use ($branch) {
                        $legacy->whereNull('branch_id')
                            ->where(function ($fallback) use ($branch) {
                                $fallback->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branch->id))
                                    ->orWhere(fn ($unbatched) => $unbatched->whereNull('batch_id')
                                        ->whereHas('user', fn ($user) => $user->where('branch_id', $branch->id)));
                            });
                    });
            });

        $onlineEnrollmentCount = OnlineBatchEnrollment::whereHas('batch', fn ($batch) => $batch->where('branch_id', $branch->id))->count();
        $coachingEnrollmentCount = CoachingEnrollment::whereHas('batch', fn ($batch) => $batch->where('branch_id', $branch->id))->count();

        // Keep the dashboard's pending balance bounded: do not hydrate every
        // order and trigger payment/adjustment queries for each row.
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
        $pendingOrderDue = DB::table('orders')
            ->leftJoinSub($verifiedPayments, 'verified_payments', fn ($join) => $join->on('verified_payments.order_id', '=', 'orders.id'))
            ->leftJoinSub($orderAdjustments, 'order_adjustments', fn ($join) => $join->on('order_adjustments.order_id', '=', 'orders.id'))
            ->where('orders.branch_id', $branch->id)
            ->whereIn('orders.status', ['pending', 'partially_paid'])
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.total - COALESCE(verified_payments.received_total, 0) + COALESCE(order_adjustments.refund_total, 0) - COALESCE(order_adjustments.credit_total, 0) > 0 THEN orders.total - COALESCE(verified_payments.received_total, 0) + COALESCE(order_adjustments.refund_total, 0) - COALESCE(order_adjustments.credit_total, 0) ELSE 0 END), 0) as due')
            ->value('due');

        $stats = [
            'students' => $studentCount,
            'teachers' => $branch->users()->where('role', 'instructor')->count(),
            'enrollments' => (clone $courseEnrollments)->count() + $onlineEnrollmentCount + $coachingEnrollmentCount,
            'revenue' => $branch->revenue(),
            'pending' => (float) $pendingOrderDue,
        ];

        $months = collect(range(5, 0))->map(function ($m) use ($branch) {
            $start = now()->subMonths($m)->startOfMonth();
            $range = [$start, $start->copy()->endOfMonth()];

            $received = (float) DB::table('payments')
                ->join('orders', 'orders.id', '=', 'payments.order_id')
                ->where('orders.branch_id', $branch->id)
                ->where('payments.status', 'verified')
                ->whereBetween('payments.created_at', $range)
                ->sum('payments.amount');

            $refunds = (float) DB::table('payment_adjustments')
                ->join('orders', 'orders.id', '=', 'payment_adjustments.order_id')
                ->where('orders.branch_id', $branch->id)
                ->where('payment_adjustments.type', 'refund')
                ->whereBetween('payment_adjustments.created_at', $range)
                ->sum('payment_adjustments.amount');

            $credits = (float) DB::table('payment_adjustments')
                ->join('orders', 'orders.id', '=', 'payment_adjustments.order_id')
                ->where('orders.branch_id', $branch->id)
                ->where('payment_adjustments.type', 'credit')
                ->whereBetween('payment_adjustments.created_at', $range)
                ->sum('payment_adjustments.amount');

            return [
                'label' => $start->format('M'),
                'amount' => max(0, round($received - $refunds + $credits, 2)),
            ];
        });

        $topCourses = DB::table('enrollments')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->leftJoin('batches', 'batches.id', '=', 'enrollments.batch_id')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->where(function ($query) use ($branch) {
                $query->where('enrollments.branch_id', $branch->id)
                    ->orWhere(function ($legacy) use ($branch) {
                        $legacy->whereNull('enrollments.branch_id')
                            ->where(function ($fallback) use ($branch) {
                                $fallback->where('batches.branch_id', $branch->id)
                                    ->orWhere(fn ($unbatched) => $unbatched->whereNull('enrollments.batch_id')
                                        ->where('users.branch_id', $branch->id));
                            });
                    });
            })
            ->select('courses.title', DB::raw('COUNT(*) as total'), DB::raw('SUM(enrollments.price_paid) as revenue'))
            ->groupBy('courses.id', 'courses.title')
            ->orderByDesc('total')->take(5)->get();

        return view('branch.dashboard', [
            'branch' => $branch,
            'stats' => $stats,
            'months' => $months,
            'topCourses' => $topCourses,
            'recentStudents' => $branch->students()->latest()->take(8)->get(),
            'recentOrders' => Order::where('branch_id', $branch->id)->with('user')->latest()->take(8)->get(),
        ]);
    }

    public function students(Request $request)
    {
        $branch = $this->branch();

        $students = $branch->students()
            ->when($request->get('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->withCount(['enrollments', 'orders'])
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('branch.students', ['branch' => $branch, 'students' => $students]);
    }

    public function student(User $student)
    {
        $branch = $this->branch();
        abort_unless((int) $student->branch_id === (int) $branch->id && $student->role === 'student', 403, 'This student does not belong to your branch.');

        return view('branch.student-show', [
            'branch' => $branch,
            'student' => $student,
            'enrollments' => $student->enrollments()->with('course')->get(),
            'orders' => $student->orders()->with('items')->latest()->take(10)->get(),
            'attempts' => $student->examAttempts()->with('exam')->latest()->take(10)->get(),
            'progress' => $student->overallProgress(),
        ]);
    }

    public function staff()
    {
        $branch = $this->branch();

        return view('branch.staff', [
            'branch' => $branch,
            'staff' => $branch->users()->whereIn('role', ['instructor', 'branch_manager'])->orderBy('name')->get(),
        ]);
    }

    public function revenue()
    {
        $branch = $this->branch();

        return view('branch.revenue', [
            'branch' => $branch,
            'orders' => Order::where('branch_id', $branch->id)->with(['user', 'items', 'payments', 'adjustments'])
                ->latest()->paginate(20),
            'total' => $branch->revenue(),
        ]);
    }
}
