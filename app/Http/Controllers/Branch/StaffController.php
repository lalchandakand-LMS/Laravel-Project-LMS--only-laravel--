<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    private function branch(): Branch
    {
        $user = auth()->user();
        $branch = $user->isBranchManager()
            ? Branch::where('manager_id', $user->id)->first()
            : ($user->branch ?? null);

        abort_unless($branch, 403, 'No branch is assigned to this account.');
        abort_unless($branch->is_active, 403, 'This branch is closed to staff changes.');

        return $branch;
    }

    private function staffMember(Branch $branch, User $staff): User
    {
        abort_unless($staff->role === 'instructor' && (int) $staff->branch_id === (int) $branch->id, 404);
        return $staff;
    }

    public function index()
    {
        $branch = $this->branch();
        $staff = $branch->users()->where('role', 'instructor')->orderBy('name')->paginate(20);

        return view('branch.staff', compact('branch', 'staff'));
    }

    public function create()
    {
        return view('branch.staff-form', ['branch' => $this->branch(), 'staff' => new User()]);
    }

    public function store(Request $request)
    {
        $branch = $this->branch();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        User::create($data + [
            'role' => 'instructor',
            'branch_id' => $branch->id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('branch.staff')->with('success', 'Teacher account created and assigned to this branch.');
    }

    public function edit(User $staff)
    {
        $branch = $this->branch();
        $staff = $this->staffMember($branch, $staff);

        return view('branch.staff-form', compact('branch', 'staff'));
    }

    public function update(Request $request, User $staff)
    {
        $branch = $this->branch();
        $staff = $this->staffMember($branch, $staff);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'password' => ['nullable', 'string', Password::min(8)],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $staff->update($data + [
            'role' => 'instructor',
            'branch_id' => $branch->id,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('branch.staff')->with('success', 'Teacher profile updated.');
    }

    public function toggle(User $staff)
    {
        $branch = $this->branch();
        $staff = $this->staffMember($branch, $staff);
        $staff->update(['is_active' => ! $staff->is_active]);

        return back()->with('success', $staff->name.' is now '.($staff->is_active ? 'active' : 'inactive').'.');
    }
}
