<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index() { return view('products.index', ['products' => Product::with('category')->orderBy('name')->paginate(20)]); }
    public function create() { return view('products.form', ['product' => new Product, 'categories' => Category::orderBy('name')->get()]); }
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);
            if ($data['stock']) StockMovement::create(['product_id' => $product->id, 'user_id' => $request->user()->id, 'type' => 'opening', 'quantity_delta' => $data['stock'], 'balance_after' => $data['stock'], 'note' => 'Opening stock']);
            return $product;
        });
        return redirect()->route('products.index')->with('success', $product->name.' created.');
    }
    public function show(Product $product) { return redirect()->route('products.edit', $product); }
    public function edit(Product $product) { return view('products.form', ['product' => $product, 'categories' => Category::orderBy('name')->get()]); }
    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        DB::transaction(function () use ($data, $product, $request) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $stock = $data['stock']; unset($data['stock']); $locked->update($data);
            if ($stock !== $locked->stock) {
                $delta = $stock - $locked->stock; $locked->update(['stock' => $stock]);
                StockMovement::create(['product_id' => $locked->id, 'user_id' => $request->user()->id, 'type' => 'adjustment', 'quantity_delta' => $delta, 'balance_after' => $stock, 'note' => 'Manual inventory adjustment']);
            }
        });
        return redirect()->route('products.index')->with('success', 'Product updated.');
    }
    public function destroy(Product $product) { $product->update(['active' => false]); return redirect()->route('products.index')->with('success', 'Product deactivated.'); }
    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate(['name' => ['required','string','max:160'], 'sku' => ['required','string','max:80', Rule::unique('products','sku')->ignore($product)], 'barcode' => ['nullable','string','max:80', Rule::unique('products','barcode')->ignore($product)], 'category_id' => ['nullable','exists:categories,id'], 'price_minor' => ['required','integer','min:0','max:1000000000000'], 'stock' => ['required','integer','min:0','max:1000000000'], 'active' => ['sometimes','boolean']]);
    }
}
