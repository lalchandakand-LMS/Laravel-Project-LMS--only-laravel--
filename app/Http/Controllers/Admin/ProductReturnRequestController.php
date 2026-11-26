<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductReturnRequestController extends Controller
{
    public function index()
    {
        $returns = ProductReturnRequest::with(['order', 'item', 'user', 'reviewer', 'receiver'])
            ->latest()
            ->paginate(25);

        return view('admin.product-returns.index', compact('returns'));
    }

    public function update(Request $request, ProductReturnRequest $returnRequest)
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,received'],
            'admin_note' => ['nullable', 'string', 'max:1200'],
            'restock' => ['sometimes', 'boolean'],
        ]);

        if ($data['status'] === 'rejected' && blank($data['admin_note'] ?? null)) {
            throw ValidationException::withMessages([
                'admin_note' => 'Add a reason when rejecting a customer return.',
            ]);
        }

        DB::transaction(function () use ($request, $returnRequest, $data) {
            $locked = ProductReturnRequest::with(['item', 'order'])
                ->whereKey($returnRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($data['status'] === 'approved') {
                if ($locked->status !== 'requested') {
                    throw ValidationException::withMessages(['status' => 'Only new return requests can be approved.']);
                }

                $locked->update([
                    'status' => 'approved',
                    'admin_note' => $data['admin_note'] ?? null,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);

                return;
            }

            if ($data['status'] === 'rejected') {
                if (! in_array($locked->status, ['requested', 'approved'], true)) {
                    throw ValidationException::withMessages(['status' => 'This return request can no longer be rejected.']);
                }

                $locked->update([
                    'status' => 'rejected',
                    'admin_note' => $data['admin_note'],
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);

                return;
            }

            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Only approved returns can be marked as received.']);
            }

            $restock = $request->boolean('restock');
            if ($restock) {
                $product = Product::withTrashed()->whereKey($locked->item->item_id)->lockForUpdate()->first();
                if (! $product) {
                    throw ValidationException::withMessages([
                        'restock' => 'The original product record is missing. Resolve inventory manually before closing this return.',
                    ]);
                }

                $product->increment('stock_quantity', $locked->quantity);
            }

            $locked->update([
                'status' => 'received',
                'admin_note' => $data['admin_note'] ?? $locked->admin_note,
                'received_by' => $request->user()->id,
                'received_at' => now(),
                'restock_quantity' => $restock ? $locked->quantity : 0,
                'restocked_at' => $restock ? now() : null,
            ]);
        });

        return back()->with('success', 'Return request updated. Use the order refund ledger separately if money must be returned.');
    }

    public function evidence(ProductReturnRequest $returnRequest)
    {
        abort_unless($returnRequest->evidence_path, 404);
        abort_unless(Storage::disk('local')->exists($returnRequest->evidence_path), 404);

        return Storage::disk('local')->download($returnRequest->evidence_path);
    }
}
