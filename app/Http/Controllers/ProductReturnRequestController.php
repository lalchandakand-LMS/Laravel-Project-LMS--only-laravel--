<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturnRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductReturnRequestController extends Controller
{
    public function index(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->load(['items.returnRequests', 'returnRequests.item', 'returnRequests.reviewer']);
        $windowDays = $this->returnWindowDays();
        $eligible = $this->returnsEnabled()
            && $order->status === 'paid'
            && $order->delivered_at
            && $order->delivered_at->greaterThanOrEqualTo(now()->subDays($windowDays));

        $items = $order->items->where('item_type', 'product')->map(function (OrderItem $item) {
            $reservedQuantity = $item->returnRequests
                ->whereIn('status', ['requested', 'approved', 'received'])
                ->sum('quantity');
            $item->returnable_quantity = max(0, (int) $item->qty - (int) $reservedQuantity);

            return $item;
        });

        return view('orders.returns.index', [
            'order' => $order,
            'items' => $items,
            'returnRequests' => $order->returnRequests->sortByDesc('created_at'),
            'returnsEnabled' => $this->returnsEnabled(),
            'returnWindowDays' => $windowDays,
            'eligible' => $eligible,
        ]);
    }

    public function store(Request $request, Order $order, OrderItem $item)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($item->order_id === $order->id, 404);

        if (! $this->returnsEnabled()) {
            throw ValidationException::withMessages(['return' => 'Product returns are currently disabled by the Admin.']);
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'reason' => ['required', 'string', 'min:8', 'max:1200'],
            'evidence' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $evidencePath = $request->file('evidence')?->store('product-return-evidence', 'local');

        try {
            DB::transaction(function () use ($request, $order, $item, $data, $evidencePath) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $lockedItem = OrderItem::whereKey($item->id)->where('order_id', $lockedOrder->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->user_id !== $request->user()->id
                    || $lockedOrder->status !== 'paid'
                    || ! $lockedOrder->delivered_at
                    || $lockedOrder->delivered_at->lt(now()->subDays($this->returnWindowDays()))
                    || $lockedItem->item_type !== 'product') {
                    throw ValidationException::withMessages([
                        'return' => 'Only delivered physical products from a paid order can be returned within the return window.',
                    ]);
                }

                $alreadyRequested = ProductReturnRequest::where('order_item_id', $lockedItem->id)
                    ->whereIn('status', ['requested', 'approved', 'received'])
                    ->sum('quantity');
                $remaining = max(0, (int) $lockedItem->qty - (int) $alreadyRequested);

                if ((int) $data['quantity'] > $remaining) {
                    throw ValidationException::withMessages([
                        'quantity' => "Only {$remaining} unit(s) remain eligible for a return request.",
                    ]);
                }

                ProductReturnRequest::create([
                    'order_id' => $lockedOrder->id,
                    'order_item_id' => $lockedItem->id,
                    'user_id' => $request->user()->id,
                    'quantity' => $data['quantity'],
                    'reason' => trim($data['reason']),
                    'evidence_path' => $evidencePath,
                    'status' => 'requested',
                ]);
            });
        } catch (\Throwable $exception) {
            if ($evidencePath) {
                Storage::disk('local')->delete($evidencePath);
            }

            throw $exception;
        }

        return redirect()->route('orders.returns.index', $order->order_no)
            ->with('success', 'Return request submitted. The branch will review it before approving a return.');
    }

    private function returnsEnabled(): bool
    {
        return (string) Setting::get('physical_returns_enabled', '1') !== '0';
    }

    private function returnWindowDays(): int
    {
        return max(1, min(30, (int) Setting::get('physical_return_window_days', 14)));
    }
}
