<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $category = \App\Models\Category::create(['name' => 'Beverages', 'slug' => 'beverages']);
        \App\Models\Product::create(['category_id' => $category->id, 'name' => 'House Coffee', 'sku' => 'BEV-COF-001', 'barcode' => '000000000001', 'price_minor' => 450, 'stock' => 40]);
        \App\Models\Product::create(['category_id' => $category->id, 'name' => 'Green Tea', 'sku' => 'BEV-TEA-001', 'barcode' => '000000000002', 'price_minor' => 325, 'stock' => 30]);
        \App\Models\Product::create(['category_id' => $category->id, 'name' => 'Sparkling Water', 'sku' => 'BEV-WAT-001', 'barcode' => '000000000003', 'price_minor' => 200, 'stock' => 50]);
    }
}
