<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function fixture(): array
    {
        $u = User::factory()->create();
        $u->role_id = Role::where('name', 'Bodeguero')->value('id');
        $u->save();
        $s = Supplier::create(['name' => 'Distribuidora', 'identification' => '1790000001']);
        $p = Product::create(['sku' => 'P-1', 'name' => 'Producto', 'category_id' => Category::create(['name' => 'General'])->id, 'purchase_price' => '4.00', 'sale_price' => '8.00', 'tax_basis_points' => 1500]);

        return [$u, $s, $p];
    }

    public function test_purchase_receipt_is_idempotent_and_tracks_stock(): void
    {
        [$u,$s,$p] = $this->fixture();
        $service = app(PurchaseService::class);
        $purchase = $service->create(['supplier_id' => $s->id, 'idempotency_key' => (string) \Str::uuid(), 'items' => [['product_id' => $p->id, 'quantity' => 3, 'unit_price' => '10.00', 'discount' => '0.01']]], $u);
        $this->assertSame(0, $p->fresh()->stock);
        $this->assertSame('34.49', $purchase->total);
        $service->receive($purchase->id, $u);
        $service->receive($purchase->id, $u);
        $this->assertSame(3, $p->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', ['stock_before' => 0, 'stock_after' => 3, 'quantity' => 3]);
        $this->actingAs($u)->get('/purchases/'.$purchase->id)->assertOk();
    }

    public function test_adjustment_rejects_negative_stock_and_records_reason(): void
    {
        [$u,$s,$p] = $this->fixture();
        $this->actingAs($u)->post('/inventory', ['product_id' => $p->id, 'quantity' => -1, 'reason' => 'Corrección'])->assertSessionHasErrors('stock');
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->post('/inventory', ['product_id' => $p->id, 'quantity' => 5, 'reason' => 'Conteo inicial'])->assertSessionHasNoErrors();
        $this->assertSame(5, $p->fresh()->stock);
    }

    public function test_purchase_and_inventory_pages_use_real_records(): void
    {
        [$u] = $this->fixture();
        $this->actingAs($u)->get('/purchases')->assertOk();
        $this->get('/purchases/create')->assertOk();
        $this->get('/inventory')->assertOk()->assertSee('Producto');
    }

    public function test_money_uses_exact_half_up_tax(): void
    {
        $this->assertSame(1, Money::tax(10, 500));
        $this->assertSame('0.30', Money::decimal(Money::cents('0.10') + Money::cents('0.20')));
    }
}
