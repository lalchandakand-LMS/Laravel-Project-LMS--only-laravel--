<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::with('manager')
            ->withCount(['users', 'students'])
            ->when($request->get('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")))
            ->orderBy('name')
            ->paginate(12)->withQueryString();

        return view('admin.branches.index', [
            'branches' => $branches,
            'totalRevenue' => (float) \App\Models\Order::where('status', 'paid')->sum('total'),
        ]);
    }

    public function create()
    {
        return view('admin.branches.form', [
            'branch' => new Branch(['is_active' => true, 'payment_methods' => []]),
            'managers' => $this->managers(),
            'paymentMethods' => Branch::PAYMENT_METHODS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);\n        DB::transaction(function () use ($data) {\n            $branch = Branch::create($data);\n            $this->syncManagerAssignment($branch, null);\n        });

        return redirect()->route('admin.branches.index')->with('success', 'Branch created.');
    }

    public function edit(Branch $branch)
    {
        return view('admin.branches.form', [
            'branch' => $branch,
            'managers' => $this->managers(),
            'paymentMethods' => Branch::PAYMENT_METHODS,
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $this->validated($request, $branch);\n        DB::transaction(function () use ($branch, $data) {\n            $oldManagerId = $branch->manager_id;\n            $branch->update($data);\n            $this->syncManagerAssignment($branch, $oldManagerId);\n        });

        return redirect()->route('admin.branches.index')->with('success', 'Branch updated.');
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return redirect()->route('admin.branches.index')->with('success', 'Branch moved to the Recycle Bin.');
    }

    public function toggle(Branch $branch)
    {
        $branch->update(['is_active' => ! $branch->is_active]);

        return back()->with('success', $branch->name.' is now '.($branch->is_active ? 'active' : 'inactive').'.');
    }

    private function managers()
    {
        return User::where('role', 'branch_manager')->orderBy('name')->get();
    }

    private function syncManagerAssignment(Branch $branch, ?int $oldManagerId): void
    {
        $newManagerId = $branch->manager_id;

        if ($oldManagerId && $oldManagerId !== $newManagerId) {
            User::whereKey($oldManagerId)
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null]);
        }

        if ($newManagerId) {
            User::whereKey($newManagerId)->update(['branch_id' => $branch->id]);
        }
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:120',
            'manager_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'branch_manager')), Rule::unique('branches', 'manager_id')->ignore($branch?->id)],
            'payment_methods' => 'sometimes|array',
            'payment_methods.*' => ['string', Rule::in(array_keys(Branch::PAYMENT_METHODS))],
            'bkash_number' => 'nullable|string|max:40',
            'nagad_number' => 'nullable|string|max:40',
            'rocket_number' => 'nullable|string|max:40',
            'bank_name' => 'nullable|string|max:120',
            'bank_branch' => 'nullable|string|max:120',
            'bank_account_name' => 'nullable|string|max:120',
            'bank_account_number' => 'nullable|string|max:80',
            'payment_instructions' => 'nullable|string|max:3000',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['payment_methods'] = array_values(array_unique($data['payment_methods'] ?? []));

        return $data;
    }
}
