<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::query()->with('branch')
            ->withCount([
                'orders as reserved_orders_count' => fn ($query) => $query->whereNotIn('status', ['cancelled', 'failed', 'refunded']),
                'orders as paid_orders_count' => fn ($query) => $query->where('status', 'paid'),
            ])->latest()->paginate(20);

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create()
    {
        return view('admin.coupons.form', $this->formData(new Coupon(['is_active' => true, 'type' => 'percent'])));
    }

    public function store(Request $request)
    {
        Coupon::create($this->validated($request));
        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupons.form', $this->formData($coupon));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $this->validated($request, $coupon);

        if ($coupon->orders()->exists()) {
            $protectedFields = ['code', 'type', 'value', 'min_amount', 'max_discount', 'branch_id', 'course_ids', 'category_ids', 'ebook_ids'];
            $hasChangedTerms = collect($protectedFields)->contains(function (string $field) use ($coupon, $data): bool {
                $current = $coupon->getAttribute($field);
                $next = $data[$field] ?? null;

                if (in_array($field, ['course_ids', 'category_ids', 'ebook_ids'], true)) {
                    $current = array_map('intval', $current ?? []);
                    $next = array_map('intval', $next ?? []);
                    sort($current);
                    sort($next);

                    return $current !== $next;
                }

                if (in_array($field, ['value', 'min_amount', 'max_discount'], true)) {
                    return ($current === null ? null : (float) $current) !== ($next === null ? null : (float) $next);
                }

                if ($field === 'branch_id') {
                    return ($current === null ? null : (int) $current) !== ($next === null ? null : (int) $next);
                }

                return (string) $current !== (string) $next;
            });

            if ($hasChangedTerms) {
                return back()->withErrors([
                    'coupon' => 'Discount terms and targeting cannot be changed after the coupon has order history. Deactivate it and create a new coupon instead.',
                ])->withInput();
            }
        }

        $coupon->update($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->orders()->exists()) {
            return back()->with('error', 'This coupon has order history. Deactivate it instead to preserve financial records.');
        }

        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }

    public function usage(Request $request)
    {
        $request->validate([
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'status' => ['nullable', 'in:pending,partially_paid,paid,failed,cancelled,refunded'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $orders = $this->usageQuery($request)->with(['coupon', 'user', 'branch'])->paginate(30)->withQueryString();

        return view('admin.coupons.usage', [
            'orders' => $orders,
            'coupons' => Coupon::orderBy('code')->get(['id', 'code']),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function exportUsage(Request $request): StreamedResponse
    {
        $request->validate([
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'status' => ['nullable', 'in:pending,partially_paid,paid,failed,cancelled,refunded'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $orders = $this->usageQuery($request)
            ->with(['coupon', 'user', 'branch'])
            ->reorder('id');

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Coupon', 'Date', 'Student', 'Email', 'Branch', 'Subtotal', 'Discount', 'Total', 'Status']);

            foreach ($orders->lazyById(500) as $order) {
                fputcsv($out, [
                    $this->safeCsvCell($order->order_no),
                    $this->safeCsvCell($order->coupon?->code),
                    $this->safeCsvCell($order->created_at?->toDateTimeString()),
                    $this->safeCsvCell($order->user?->name),
                    $this->safeCsvCell($order->user?->email),
                    $this->safeCsvCell($order->branch?->name),
                    $order->subtotal,
                    $order->discount,
                    $order->total,
                    $this->safeCsvCell($order->status),
                ]);
            }

            fclose($out);
        }, 'coupon-usage-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function usageQuery(Request $request)
    {
        return Order::query()->whereNotNull('coupon_id')
            ->when($request->filled('coupon_id'), fn ($query) => $query->where('coupon_id', $request->integer('coupon_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest();
    }

    private function formData(Coupon $coupon): array
    {
        return [
            'coupon' => $coupon,
            'branches' => Branch::orderBy('name')->get(),
            'courses' => Course::where('is_published', true)->orderBy('title')->get(['id', 'title']),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ebooks' => Ebook::where('is_published', true)->orderBy('title')->get(['id', 'title']),
        ];
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'max_discount' => ['nullable', 'numeric', 'gt:0', 'max:100000000'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'per_user_limit' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'distinct', 'exists:courses,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'ebook_ids' => ['nullable', 'array'],
            'ebook_ids.*' => ['integer', 'distinct', 'exists:ebooks,id'],
            'first_order_only' => ['nullable', 'boolean'],
        ]);

        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'value' => 'Percentage discount cannot exceed 100%.',
            ]);
        }

        $data['min_amount'] = $data['min_amount'] ?? 0;
        $data['usage_limit'] = $data['usage_limit'] ?? 0;
        $data['per_user_limit'] = $data['per_user_limit'] ?? 0;
        $data['max_discount'] = $data['max_discount'] ?? null;
        $data['branch_id'] = $data['branch_id'] ?? null;
        $data['course_ids'] = array_values($data['course_ids'] ?? []);
        $data['category_ids'] = array_values($data['category_ids'] ?? []);
        $data['ebook_ids'] = array_values($data['ebook_ids'] ?? []);
        $data['first_order_only'] = $request->boolean('first_order_only');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
    private function safeCsvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[\\x00-\\x20]*[=+\\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }

}
