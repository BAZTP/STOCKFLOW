<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SqlServerCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku, ?string $barcode = null): Product
    {
        return Product::create([
            'sku' => $sku, 'barcode' => $barcode, 'name' => 'Café – edición especial',
            'category_id' => Category::firstOrCreate(['name' => 'Alimentación'])->id,
            'purchase_price' => '5.25', 'sale_price' => '10.50',
        ]);
    }

    public function test_multiple_products_can_have_no_barcode_and_preserve_unicode_and_decimals(): void
    {
        $this->product('NULL-1');
        $product = $this->product('NULL-2')->fresh();
        $this->assertDatabaseCount('products', 2);
        $this->assertNull($product->barcode);
        $this->assertSame('Café – edición especial', $product->name);
        $this->assertSame('5.25', $product->purchase_price);
    }

    public function test_non_null_barcode_is_still_unique(): void
    {
        $this->product('BAR-1', '12345678');
        $this->expectException(UniqueConstraintViolationException::class);
        $this->product('BAR-2', '12345678');
    }
}
