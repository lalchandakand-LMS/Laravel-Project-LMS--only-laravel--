<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index()
    {
        return view('admin.products.index', [
            'products' => Product::withTrashed()->latest()->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.products.form', ['product' => new Product(['is_published' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $image = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image')->store('products', 'public');
            if (! $image) {
                throw ValidationException::withMessages(['image' => 'Product image could not be saved.']);
            }
            $data['image'] = $image;
        }

        try {
            Product::create($data);
        } catch (\Throwable $exception) {
            if ($image) {
                delete_stored_media('public', $image);
            }
            throw $exception;
        }

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        $oldImage = $product->image;
        $newImage = null;

        if ($request->hasFile('image')) {
            $newImage = $request->file('image')->store('products', 'public');
            if (! $newImage) {
                throw ValidationException::withMessages(['image' => 'Product image could not be saved. The existing image was kept.']);
            }
            $data['image'] = $newImage;
        }

        try {
            DB::transaction(fn () => $product->update($data));
        } catch (\Throwable $exception) {
            if ($newImage) {
                delete_stored_media('public', $newImage);
            }
            throw $exception;
        }

        if ($newImage && $oldImage) {
            delete_stored_media('public', $oldImage);
        }

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        abort_if(DB::table('order_items')->where('item_type', 'product')->where('item_id', $product->id)->exists(), 409,
            'This product has order history. Unpublish it instead of deleting its purchase record.');

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product archived.');
    }

    public function restore(int $product)
    {
        $record = Product::withTrashed()->findOrFail($product);
        $record->restore();

        return back()->with('success', 'Product restored.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $request->merge(['is_published' => $request->boolean('is_published')]);

        $data = $request->validate([
            'title' => 'required|string|max:180',
            'slug' => 'nullable|string|max:200|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|unique:products,slug,'.($product?->id ?? 'NULL').',id',
            'sku' => 'nullable|string|max:80|unique:products,sku,'.($product?->id ?? 'NULL').',id',
            'description' => 'nullable|string|max:20000',
            'price' => 'required|numeric|min:0|max:99999999',
            'stock_quantity' => 'required|integer|min:0|max:10000000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'is_published' => 'required|boolean',
        ]);

        $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Str::slug($data['title']);

        return $data;
    }
}
