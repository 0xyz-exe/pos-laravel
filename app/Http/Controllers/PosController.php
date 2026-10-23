<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $products = \App\Models\Product::query()->with('category')->where('active', true)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%')->orWhere('sku', 'like', '%'.$request->string('q').'%')->orWhere('barcode', $request->string('q'))))
            ->orderBy('name')->paginate(20)->withQueryString();
        $salesQuery = \App\Models\Sale::query()->whereNull('voided_at')->where('sold_at', '>=', now()->subDays(30));
        if (!$request->user()->is_admin) $salesQuery->where('cashier_id', $request->user()->id);
        $report = $salesQuery->selectRaw('count(*) as count, coalesce(sum(total_minor), 0) as gross_minor')->first();
        $sales = \App\Models\Sale::with('cashier')->when(!$request->user()->is_admin, fn ($q) => $q->where('cashier_id', $request->user()->id))->latest('sold_at')->paginate(10, ['*'], 'sales_page');
        return view('pos.index', compact('products', 'sales', 'report'));
    }

    public function checkout(\App\Http\Requests\CheckoutRequest $request)
    {
        $data = $request->validated();
        $sale = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
            $requested = collect($data['items'])->keyBy('product_id');
            $products = \App\Models\Product::query()->whereIn('id', $requested->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $requested->count()) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'One or more products are unavailable.']);
            $subtotal = 0; $lines = [];
            foreach ($requested as $id => $item) {
                $product = $products->get($id);
                if (!$product->active || $product->stock < $item['quantity']) throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Insufficient stock for {$product->name}."]);
                $line = $product->price_minor * $item['quantity'];
                if ($line > PHP_INT_MAX - $subtotal) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Sale total exceeds supported limit.']);
                $subtotal += $line; $lines[] = [$product, $item['quantity'], $line];
            }
            $discount = $data['discount_minor'] ?? 0;
            if ($discount > 0 && !$request->user()->is_admin) throw \Illuminate\Validation\ValidationException::withMessages(['discount_minor' => 'Only administrators may apply discounts.']);
            if ($discount > $subtotal) throw \Illuminate\Validation\ValidationException::withMessages(['discount_minor' => 'Discount cannot exceed subtotal.']);
            $taxable = $subtotal - $discount;
            $basisPoints = max(0, min(10000, (int) config('pos.tax_basis_points', 0)));
            $tax = intdiv($taxable, 10000) * $basisPoints + intdiv(($taxable % 10000) * $basisPoints + 5000, 10000);
            $sale = \App\Models\Sale::create(['cashier_id' => $request->user()->id, 'receipt_number' => 'POS-'.now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(8)), 'payment_method' => $data['payment_method'], 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'tax_minor' => $tax, 'total_minor' => $taxable + $tax]);
            foreach ($lines as [$product, $quantity, $line]) {
                $product->decrement('stock', $quantity);
                $sale->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'quantity' => $quantity, 'unit_price_minor' => $product->price_minor, 'line_total_minor' => $line]);
                \App\Models\StockMovement::create(['product_id' => $product->id, 'user_id' => $request->user()->id, 'sale_id' => $sale->id, 'type' => 'sale', 'quantity_delta' => -$quantity, 'balance_after' => $product->stock, 'note' => $sale->receipt_number]);
            }
            return $sale;
        });
        return redirect()->route('pos.receipt', $sale)->with('success', 'Sale completed.');
    }

    public function void(\Illuminate\Http\Request $request, \App\Models\Sale $sale)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $sale, $data) {
            $lockedSale = \App\Models\Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($lockedSale->isVoided()) throw \Illuminate\Validation\ValidationException::withMessages(['sale' => 'Sale already voided.']);
            foreach ($lockedSale->items()->orderBy('product_id')->get() as $item) {
                $product = \App\Models\Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                $product->increment('stock', $item->quantity);
                \App\Models\StockMovement::create(['product_id' => $product->id, 'user_id' => $request->user()->id, 'sale_id' => $lockedSale->id, 'type' => 'void', 'quantity_delta' => $item->quantity, 'balance_after' => $product->stock, 'note' => $data['reason']]);
            }
            $lockedSale->update(['voided_at' => now(), 'voided_by_id' => $request->user()->id, 'void_reason' => $data['reason']]);
        });
        return redirect()->route('pos.receipt', $sale)->with('success', 'Sale voided and stock restored.');
    }

    public function receipt(\Illuminate\Http\Request $request, \App\Models\Sale $sale)
    {
        abort_unless($sale->cashier_id === $request->user()->id || $request->user()->is_admin, 403);
        return view('pos.receipt', ['sale' => $sale->load('items', 'cashier')]);
    }
}
