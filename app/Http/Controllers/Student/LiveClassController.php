<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LiveClass;
use App\Models\User;

class LiveClassController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isStudent() || $user->isAdmin(), 403);

        $classes = LiveClass::query()
            ->with('course')
            ->where('is_published', true)
            ->whereNotNull('starts_at')
            ->whereHas('course', function ($course) use ($user) {
                $course->where('is_published', true);

                if ($user->isAdmin()) {
                    return;
                }

                $course->whereHas('enrollments', function ($enrollments) use ($user) {
                    $enrollments->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                        ->where(function ($batch) use ($user) {
                            $batch->whereNull('batch_id')
                                ->orWhereHas('batch', function ($eligibleBatch) use ($user) {
                                    $eligibleBatch->whereNotIn('status', ['upcoming', 'cancelled'])
                                        ->where(function ($branch) use ($user) {
                                            $branch->whereNull('branch_id')
                                                ->orWhere('branch_id', $user->branch_id);
                                        });
                                });
                        });
                });
            })
            ->orderByRaw('CASE WHEN starts_at >= ? THEN 0 ELSE 1 END', [now()])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [now()])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [now()])
            ->paginate(12);

        return view('student.live-classes', compact('classes'));
    }
}
