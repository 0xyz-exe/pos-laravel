<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_server_prices_decrements_stock_and_generates_receipt(): void
    {
        $cashier = User::factory()->create();
        $cashier->forceFill(['is_admin' => true])->save();
        config(['pos.tax_basis_points' => 1000]);
        $product = $this->product(['price_minor' => 499, 'stock' => 5]);
        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash', 'discount_minor' => 99,
        ])->assertRedirect();

        $sale = Sale::with('items')->sole();
        $this->assertSame(998, $sale->subtotal_minor);
        $this->assertSame(99, $sale->discount_minor);
        $this->assertSame(90, $sale->tax_minor);
        $this->assertSame(989, $sale->total_minor);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(-2, StockMovement::sole()->quantity_delta);
        $this->get(route('pos.receipt', $sale))->assertOk()->assertSee($sale->receipt_number);
    }

    public function test_insufficient_stock_rejects_checkout_without_partial_sale(): void
    {
        $cashier = User::factory()->create();
        $product = $this->product(['stock' => 1]);
        $this->actingAs($cashier)->from(route('pos.index'))->post(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]], 'payment_method' => 'cash',
        ])->assertSessionHasErrors('items');

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cashier_cannot_apply_administrative_discount(): void
    {
        $cashier = User::factory()->create();
        $product = $this->product();
        $this->actingAs($cashier)->from(route('pos.index'))->post(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]], 'payment_method' => 'cash', 'discount_minor' => 1,
        ])->assertSessionHasErrors('discount_minor');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_cashier_cannot_view_another_cashiers_receipt(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = $this->product();
        $sale = Sale::create(['cashier_id' => $owner->id, 'receipt_number' => 'POS-TEST-0001', 'payment_method' => 'cash', 'subtotal_minor' => 200, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 200]);
        $sale->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'quantity' => 1, 'unit_price_minor' => 200, 'line_total_minor' => 200]);

        $this->actingAs($other)->get(route('pos.receipt', $sale))->assertForbidden();
    }

    public function test_admin_can_void_sale_once_and_restore_inventory(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $product = $this->product(['stock' => 8]);
        $sale = Sale::create(['cashier_id' => $admin->id, 'receipt_number' => 'POS-VOID-0001', 'payment_method' => 'cash', 'subtotal_minor' => 400, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 400]);
        $sale->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'quantity' => 2, 'unit_price_minor' => 200, 'line_total_minor' => 400]);
        $product->decrement('stock', 2);

        $this->actingAs($admin)->post(route('sales.void', $sale), ['reason' => 'Incorrect item'])->assertRedirect();
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['type' => 'void', 'quantity_delta' => 2, 'balance_after' => 8]);
        $this->assertSame('Incorrect item', $sale->fresh()->void_reason);
        $this->actingAs($admin)->post(route('sales.void', $sale), ['reason' => 'Duplicate'])->assertSessionHasErrors('sale');
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_administrator_can_manage_categories(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->post(route('categories.store'), ['name' => 'Cold Drinks'])->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Cold Drinks', 'slug' => 'cold-drinks']);
        $this->actingAs($admin)->get(route('categories.index'))->assertOk()->assertSee('Cold Drinks');
    }

    public function test_product_management_is_admin_only(): void
    {
        $cashier = User::factory()->create();
        $this->actingAs($cashier)->get(route('products.index'))->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get(route('products.index'))->assertOk();
    }

    private function product(array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Test goods', 'slug' => 'test-goods']);
        return Product::create(array_merge(['category_id' => $category->id, 'name' => 'Sample item', 'sku' => 'SKU-'.uniqid(), 'price_minor' => 200, 'stock' => 4], $overrides));
    }
}
