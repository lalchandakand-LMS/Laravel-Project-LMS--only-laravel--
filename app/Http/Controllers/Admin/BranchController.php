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
        Branch::create($this->validated($request));

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
        $branch->update($this->validated($request, $branch));

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
        return User::whereIn('role', ['branch_manager', 'admin'])->orderBy('name')->get();
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:120',
            'manager_id' => 'nullable|exists:users,id',
            'payment_methods' => 'sometimes|array',
            'payment_methods.*' => ['string', Rule::in(array_keys(Branch::PAYMENT_METHODS))],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['payment_methods'] = array_values(array_unique($data['payment_methods'] ?? []));

        return $data;
    }
}
