<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PeopleController extends Controller
{
    private function branch(): Branch
    {
        $user = auth()->user();
        $branch = $user->branch ?? Branch::where('manager_id', $user->id)->first();

        abort_unless($branch, 403, 'No branch is assigned to this account.');

        return $branch;
    }

    public function create(string $type)
    {
        abort_unless(in_array($type, ['student', 'staff'], true), 404);

        return view('branch.people-form', [
            'branch' => $this->branch(),
            'user' => new User(['role' => $type === 'student' ? 'student' : 'instructor', 'is_active' => true]),
            'type' => $type,
        ]);
    }

    public function store(Request $request, string $type)
    {
        abort_unless(in_array($type, ['student', 'staff'], true), 404);
        $data = $this->validatePerson($request, $type);
        $branch = $this->branch();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => $type === 'student' ? 'student' : 'instructor',
            'branch_id' => $branch->id,
            'designation' => $data['designation'] ?? null,
            'is_active' => true,
        ]);

        $this->saveProfile($user, $data, $type);

        return redirect()->route($type === 'student' ? 'branch.students' : 'branch.staff')
            ->with('success', ucfirst($type).' created in '.$branch->name.'.');
    }

    public function edit(User $user)
    {
        $branch = $this->branch();
        abort_unless($user->branch_id === $branch->id && in_array($user->role, ['student', 'instructor'], true), 404);

        return view('branch.people-form', [
            'branch' => $branch,
            'user' => $user->load(['studentProfile', 'staffProfile']),
            'type' => $user->isStudent() ? 'student' : 'staff',
        ]);
    }

    public function update(Request $request, User $user)
    {
        $branch = $this->branch();
        abort_unless($user->branch_id === $branch->id && in_array($user->role, ['student', 'instructor'], true), 404);
        $type = $user->isStudent() ? 'student' : 'staff';
        $data = $this->validatePerson($request, $type, $user);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'designation' => $data['designation'] ?? null,
            'is_active' => $request->boolean('is_active'),
            ...(! blank($data['password'] ?? null) ? ['password' => $data['password']] : []),
        ]);

        $this->saveProfile($user, $data, $type);

        return redirect()->route($type === 'student' ? 'branch.students' : 'branch.staff')
            ->with('success', ucfirst($type).' profile updated.');
    }

    public function destroy(User $user)
    {
        $branch = $this->branch();
        abort_unless($user->branch_id === $branch->id && in_array($user->role, ['student', 'instructor'], true), 404);
        abort_if($user->is(auth()->user()), 403);

        $type = $user->isStudent() ? 'student' : 'staff';
        $user->delete();

        return redirect()->route($type === 'student' ? 'branch.students' : 'branch.staff')
            ->with('success', 'Account moved to the Recycle Bin.');
    }

    private function validatePerson(Request $request, string $type, ?User $user = null): array
    {
        $student = $type === 'student';

        return $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:120',
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
            'is_active' => 'sometimes|boolean',
            'role' => 'prohibited',
            'branch_id' => 'prohibited',
            'student_code' => [$student ? 'nullable' : 'prohibited', 'string', 'max:40', Rule::unique('student_profiles', 'student_code')->ignore($user?->studentProfile?->id)],
            'date_of_birth' => [$student ? 'nullable' : 'prohibited', 'nullable', 'date', 'before_or_equal:today'],
            'school_name' => [$student ? 'nullable' : 'prohibited', 'nullable', 'string', 'max:180'],
            'academic_class' => [$student ? 'nullable' : 'prohibited', 'nullable', 'string', 'max:80'],
            'academic_roll' => [$student ? 'nullable' : 'prohibited', 'nullable', 'string', 'max:60'],
            'academic_session' => [$student ? 'nullable' : 'prohibited', 'nullable', 'string', 'max:40'],
            'student_status' => [$student ? 'nullable' : 'prohibited', Rule::in(['active', 'inactive', 'suspended', 'graduated'])],
            'employee_code' => [$student ? 'prohibited' : 'nullable', 'string', 'max:40', Rule::unique('staff_profiles', 'employee_code')->ignore($user?->staffProfile?->id)],
            'department' => [$student ? 'prohibited' : 'nullable', 'nullable', 'string', 'max:120'],
            'staff_designation' => [$student ? 'prohibited' : 'nullable', 'nullable', 'string', 'max:120'],
            'employment_type' => [$student ? 'prohibited' : 'nullable', Rule::in(['full_time', 'part_time', 'contract', 'visiting'])],
            'joining_date' => [$student ? 'prohibited' : 'nullable', 'nullable', 'date'],
            'highest_qualification' => [$student ? 'prohibited' : 'nullable', 'nullable', 'string', 'max:180'],
            'employment_status' => [$student ? 'prohibited' : 'nullable', Rule::in(['active', 'on_leave', 'suspended', 'resigned', 'terminated'])],
        ]);
    }

    private function saveProfile(User $user, array $data, string $type): void
    {
        if ($type === 'student') {
            $user->studentProfile()->updateOrCreate([], [
                'student_code' => $data['student_code'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'school_name' => $data['school_name'] ?? null,
                'academic_class' => $data['academic_class'] ?? null,
                'academic_roll' => $data['academic_roll'] ?? null,
                'academic_session' => $data['academic_session'] ?? null,
                'status' => $data['student_status'] ?? $user->studentProfile?->status ?? 'active',
            ]);
            return;
        }

        $user->staffProfile()->updateOrCreate([], [
            'employee_code' => $data['employee_code'] ?? null,
            'department' => $data['department'] ?? null,
            'designation' => $data['staff_designation'] ?? $data['designation'] ?? null,
            'employment_type' => $data['employment_type'] ?? null,
            'joining_date' => $data['joining_date'] ?? null,
            'highest_qualification' => $data['highest_qualification'] ?? null,
            'employment_status' => $data['employment_status'] ?? $user->staffProfile?->employment_status ?? 'active',
        ]);
    }
}
