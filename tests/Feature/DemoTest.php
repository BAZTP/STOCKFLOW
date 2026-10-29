<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_real_repeatable_and_has_no_public_account(): void
    {
        $this->seed(DemoSeeder::class);
        $count = Sale::count();
        $this->assertSame(62, $count);
        $this->assertSame(20, Product::count());
        $this->assertSame(0, User::where('active', true)->count());
        $this->seed(DemoSeeder::class);
        $this->assertSame($count, Sale::count());
        $this->assertDatabaseCount('purchases', 3);
        $this->assertSame(3, Product::whereColumn('stock', '<=', 'minimum_stock')->count());
    }
}
