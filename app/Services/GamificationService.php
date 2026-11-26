<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    public static function award(User $user, string $eventType, string $eventKey, int $points, array $metadata = []): void
    {
        if ($points <= 0) {
            return;
        }

        DB::table('gamification_events')->insertOrIgnore([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'event_key' => $eventKey,
            'points' => $points,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function summary(User $user): array
    {
        $events = $user->gamificationEvents()->latest()->take(500)->get(['event_type', 'points', 'created_at']);
        $xp = (int) $user->gamificationEvents()->sum('points');
        $lessonCompletions = $user->gamificationEvents()->where('event_type', 'lesson_completed')->count();
        $passedExams = $user->gamificationEvents()->where('event_type', 'exam_passed')->count();

        $activeDates = $events->map(fn ($event) => $event->created_at->toDateString())->unique()->values();
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();
        $cursor = $activeDates->contains($today) ? today() : ($activeDates->contains($yesterday) ? today()->subDay() : null);
        $streak = 0;

        while ($cursor && $activeDates->contains($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        $badges = [
            ['key' => 'first_lesson', 'name' => 'First Step', 'description' => 'Complete your first lesson.', 'unlocked' => $lessonCompletions >= 1],
            ['key' => 'lesson_10', 'name' => 'Lesson Explorer', 'description' => 'Complete 10 lessons.', 'unlocked' => $lessonCompletions >= 10],
            ['key' => 'exam_pass', 'name' => 'Exam Achiever', 'description' => 'Pass an online exam.', 'unlocked' => $passedExams >= 1],
            ['key' => 'xp_500', 'name' => '500 XP Club', 'description' => 'Earn 500 experience points.', 'unlocked' => $xp >= 500],
        ];

        return [
            'xp' => $xp,
            'level' => intdiv($xp, 100) + 1,
            'level_progress' => $xp % 100,
            'streak_days' => $streak,
            'badges' => $badges,
            'unlocked_badges' => collect($badges)->where('unlocked', true)->count(),
        ];
    }
}
