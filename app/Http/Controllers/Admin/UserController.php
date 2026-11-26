<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['branch', 'studentProfile', 'staffProfile'])->withCount(['enrollments', 'courses', 'orders']);

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }
        if ($branch = $request->get('branch')) {
            $query->where('branch_id', $branch);
        }
        if ($status = $request->get('status')) {
            if ($request->get('role') === 'student') {
                $query->whereHas('studentProfile', fn ($profile) => $profile->where('status', $status));
            } elseif (in_array($request->get('role'), ['instructor', 'branch_manager'], true)) {
                $query->whereHas('staffProfile', fn ($profile) => $profile->where('employment_status', $status));
            } elseif ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($q = $request->get('q')) {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
        }

        return view('admin.users.index', [
            'users' => $query->latest()->paginate(20)->withQueryString(),
            'branches' => Branch::orderBy('name')->get(),
            'counts' => collect(User::ROLES)->mapWithKeys(fn ($label, $key) => [$key => User::where('role', $key)->count()])
                ->prepend(User::count(), 'all')->all(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', [
            'user' => new User(['role' => 'student', 'is_active' => true]),
            'roles' => User::ROLES,
            'branches' => Branch::orderBy('name')->get(),
            'students' => User::where('role', 'student')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $user = User::create($this->accountData($data, $request));

        $this->syncRoleProfile($user, $data);

        if ($user->isGuardian()) {
            $user->children()->sync($request->input('children', []));
        }

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function show(User $user)
    {
        return view('admin.users.show', [
            'user' => $user->load([
                'branch', 'studentProfile.documents', 'studentProfile.attendanceRecords.session',
                'staffProfile.documents', 'staffProfile.payrolls.paidBy', 'staffProfile.attendances', 'enrollments.course',
                'orders.items', 'examAttempts.exam', 'certificates.course',
                'children', 'guardians', 'courses',
            ]),
        ]);
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'user' => $user->load(['studentProfile', 'staffProfile', 'children']),
            'roles' => User::ROLES,
            'branches' => Branch::orderBy('name')->get(),
            'students' => User::where('role', 'student')->whereKeyNot($user->id)->orderBy('name')->get(),
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete your own account while signed in.');
        }
        if ($user->isAdmin() && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'The last active administrator cannot be deleted.');
        }
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Account moved to the Recycle Bin.');
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validatedData($request, $user);
        $user->update($this->accountData($data, $request, $user));

        $this->syncRoleProfile($user, $data);

        if ($user->isGuardian()) {
            $user->children()->sync($request->input('children', []));
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $student = $request->input('role') === 'student';
        $staff = in_array($request->input('role'), ['instructor', 'branch_manager'], true);

        return $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:'.implode(',', array_keys(User::ROLES)),
            'branch_id' => 'nullable|exists:branches,id',
            'designation' => 'nullable|string|max:120',
            'bio' => 'nullable|string|max:1000',
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
            'avatar_url' => 'nullable|url:http,https|max:2048',
            'is_active' => 'sometimes|boolean',
            'children' => 'nullable|array',
            'children.*' => 'integer|exists:users,id',

            'student_code' => [$student ? 'nullable' : 'prohibited', 'string', 'max:40', Rule::unique('student_profiles', 'student_code')->ignore($user?->studentProfile?->id)],
            'date_of_birth' => [$student ? 'nullable' : 'prohibited', 'date', 'before_or_equal:today'],
            'gender' => [$student ? 'nullable' : 'prohibited', 'string', 'max:30'],
            'school_name' => [$student ? 'nullable' : 'prohibited', 'string', 'max:180'],
            'academic_class' => [$student ? 'nullable' : 'prohibited', 'string', 'max:80'],
            'academic_roll' => [$student ? 'nullable' : 'prohibited', 'string', 'max:60'],
            'academic_session' => [$student ? 'nullable' : 'prohibited', 'string', 'max:40'],
            'academic_group' => [$student ? 'nullable' : 'prohibited', 'string', 'max:80'],
            'present_address' => [$student ? 'nullable' : 'prohibited', 'string', 'max:500'],
            'permanent_address' => [$student ? 'nullable' : 'prohibited', 'string', 'max:500'],
            'emergency_contact_name' => [$student ? 'nullable' : 'prohibited', 'string', 'max:160'],
            'emergency_contact_phone' => [$student ? 'nullable' : 'prohibited', 'string', 'max:30'],
            'guardian_name' => [$student ? 'nullable' : 'prohibited', 'string', 'max:160'],
            'guardian_phone' => [$student ? 'nullable' : 'prohibited', 'string', 'max:30'],
            'guardian_email' => [$student ? 'nullable' : 'prohibited', 'nullable', 'email', 'max:255'],
            'quota_type' => [$student ? 'nullable' : 'prohibited', 'string', 'max:80'],
            'quota_notes' => [$student ? 'nullable' : 'prohibited', 'string', 'max:1000'],
            'student_status' => [$student ? 'nullable' : 'prohibited', Rule::in(['active', 'inactive', 'suspended', 'graduated'])],

            'employee_code' => [$staff ? 'nullable' : 'prohibited', 'string', 'max:40', Rule::unique('staff_profiles', 'employee_code')->ignore($user?->staffProfile?->id)],
            'department' => [$staff ? 'nullable' : 'prohibited', 'string', 'max:120'],
            'staff_designation' => [$staff ? 'nullable' : 'prohibited', 'string', 'max:120'],
            'employment_type' => [$staff ? 'nullable' : 'prohibited', Rule::in(['full_time', 'part_time', 'contract', 'visiting'])],
            'joining_date' => [$staff ? 'nullable' : 'prohibited', 'date'],
            'confirmation_date' => [$staff ? 'nullable' : 'prohibited', 'date', 'after_or_equal:joining_date'],
            'experience_years' => [$staff ? 'nullable' : 'prohibited', 'integer', 'min:0', 'max:60'],
            'highest_qualification' => [$staff ? 'nullable' : 'prohibited', 'string', 'max:180'],
            'institution' => [$staff ? 'nullable' : 'prohibited', 'string', 'max:180'],
            'employment_status' => [$staff ? 'nullable' : 'prohibited', Rule::in(['active', 'on_leave', 'suspended', 'resigned', 'terminated'])],
            'professional_bio' => [$staff ? 'nullable' : 'prohibited', 'string', 'max:5000'],
        ]);
    }

    private function accountData(array $data, Request $request, ?User $user = null): array
    {
        $account = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'branch_id' => $data['branch_id'] ?? null,
            'designation' => $data['designation'] ?? null,
            'bio' => $data['bio'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if (filled($data['password'] ?? null)) {
            $account['password'] = $data['password'];
        }

        if ($request->hasFile('avatar')) {
            $account['avatar'] = $request->file('avatar')->store('avatars', 'public');
            if ($user) {
                delete_stored_media('public', $user->avatar);
            }
        } elseif ($request->filled('avatar_url')) {
            $account['avatar'] = $data['avatar_url'];
            if ($user && $user->avatar !== $account['avatar']) {
                delete_stored_media('public', $user->avatar);
            }
        }

        return $account;
    }

    private function syncRoleProfile(User $user, array $data): void
    {
        if ($user->isStudent()) {
            $user->studentProfile()->updateOrCreate([], [
                'student_code' => $data['student_code'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'school_name' => $data['school_name'] ?? null,
                'academic_class' => $data['academic_class'] ?? null,
                'academic_roll' => $data['academic_roll'] ?? null,
                'academic_session' => $data['academic_session'] ?? null,
                'academic_group' => $data['academic_group'] ?? null,
                'present_address' => $data['present_address'] ?? null,
                'permanent_address' => $data['permanent_address'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'guardian_name' => $data['guardian_name'] ?? null,
                'guardian_phone' => $data['guardian_phone'] ?? null,
                'guardian_email' => $data['guardian_email'] ?? null,
                'quota_type' => $data['quota_type'] ?? null,
                'quota_notes' => $data['quota_notes'] ?? null,
                'status' => $data['student_status'] ?? $user->studentProfile?->status ?? 'active',
            ]);
        }

        if (in_array($user->role, ['instructor', 'branch_manager'], true)) {
            $user->staffProfile()->updateOrCreate([], [
                'employee_code' => $data['employee_code'] ?? null,
                'department' => $data['department'] ?? null,
                'designation' => $data['staff_designation'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
                'joining_date' => $data['joining_date'] ?? null,
                'confirmation_date' => $data['confirmation_date'] ?? null,
                'experience_years' => $data['experience_years'] ?? null,
                'highest_qualification' => $data['highest_qualification'] ?? null,
                'institution' => $data['institution'] ?? null,
                'employment_status' => $data['employment_status'] ?? $user->staffProfile?->employment_status ?? 'active',
                'professional_bio' => $data['professional_bio'] ?? null,
            ]);
        }
    }
}
