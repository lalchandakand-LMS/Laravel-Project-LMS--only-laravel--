<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        return view('products.index', [
            'products' => Product::published()->latest()->paginate(12),
        ]);
    }

    public function show(string $slug)
    {
        $product = Product::published()->where('slug', $slug)->firstOrFail();

        return view('products.show', compact('product'));
    }
}
