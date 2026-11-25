<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $query = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->select('activity_logs.*', 'users.name as actor_name', 'users.email as actor_email')
            ->orderByDesc('activity_logs.created_at');

        if ($request->filled('event')) {
            $query->where('activity_logs.event', $request->string('event')->toString());
        }
        if ($request->filled('user_id')) {
            $query->where('activity_logs.user_id', $request->integer('user_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('activity_logs.created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('activity_logs.created_at', '<=', $request->date('to'));
        }

        return view('admin.activity-logs.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['event', 'user_id', 'from', 'to']),
        ]);
    }
}
