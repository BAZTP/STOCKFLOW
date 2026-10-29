<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['sku' => fake()->unique()->numerify('SKU-########'), 'barcode' => fake()->unique()->ean13(), 'name' => fake()->words(3, true), 'category_id' => Category::factory(), 'supplier_id' => Supplier::factory(), 'purchase_price' => '10.00', 'sale_price' => '18.50', 'tax_basis_points' => 1500, 'minimum_stock' => 5, 'unit' => 'unidad', 'active' => true];
    }
}
