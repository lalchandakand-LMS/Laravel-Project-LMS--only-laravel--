<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;

class InstructorController extends Controller
{
    public function index()
    {
        $instructors = User::query()
            ->where('role', 'instructor')
            ->where('is_active', true)
            ->with('staffProfile')
            ->withCount(['courses' => fn ($query) => $query->published()])
            ->orderByRaw('(SELECT COALESCE(sort_order, 0) FROM staff_profiles WHERE staff_profiles.user_id = users.id LIMIT 1) ASC')
            ->orderBy('name')
            ->get();

        $panels = $instructors->groupBy(fn (User $instructor) =>
            filled($instructor->staffProfile?->panel_group)
                ? $instructor->staffProfile->panel_group
                : 'General Instructor Panel'
        );

        return view('instructors.index', [
            'panels' => $panels,
            'pageTitle' => Setting::get('instructors_title', 'Our Expert Instructors'),
            'pageSubtitle' => Setting::get('instructors_subtitle', 'Meet the experienced educators guiding your learning journey.'),
        ]);
    }

    public function show(User $user)
    {
        abort_unless($user->role === 'instructor' && $user->is_active, 404);

        $user->load([
            'staffProfile',
            'courses' => fn ($query) => $query->published()->with('category')->withCount('lessons')->latest(),
        ]);

        return view('instructors.show', [
            'instructor' => $user,
            'courses' => $user->courses,
            'pageTitle' => $user->name,
            'pageSubtitle' => $user->designation ?: 'Instructor at '.Setting::get('site_name', "Alom's English Center"),
        ]);
    }
}
