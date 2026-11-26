<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Str;

class IdentityCardController extends Controller
{
    public function own(\Illuminate\Http\Request $request)
    {
        return $this->show($request->user());
    }

    public function show(User $user)
    {
        abort_unless(in_array($user->role, ['student', 'instructor', 'branch_manager'], true), 404);

        $user->load(['branch', 'studentProfile', 'staffProfile']);
        $profile = $user->isStudent()
            ? $user->studentProfile()->firstOrCreate([])
            : $user->staffProfile()->firstOrCreate([], ['employment_status' => 'active']);

        if (blank($profile->id_token)) {
            $profile->forceFill(['id_token' => Str::random(48)])->save();
        }

        return view('admin.users.id-card', [
            'user' => $user,
            'profile' => $profile,
            'verificationUrl' => route('identity.verify', $profile->id_token),
        ]);
    }

    public function verify(string $token)
    {
        $student = StudentProfile::with('user.branch')->where('id_token', $token)->first();
        $staff = $student ? null : StaffProfile::with('user.branch')->where('id_token', $token)->first();
        $profile = $student ?? $staff;
        abort_unless($profile && $profile->user && $profile->user->is_active, 404);

        return view('identity.verify', [
            'user' => $profile->user,
            'profile' => $profile,
            'role' => $student ? 'Student' : 'Staff',
            'identifier' => $student ? $profile->student_code : $profile->employee_code,
            'status' => $student ? $profile->status : $profile->employment_status,
        ]);
    }
}
