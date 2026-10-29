<?php

namespace Tests\Feature;

use App\Livewire\PointOfSale;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SaleTest extends TestCase
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
        $u->role_id = Role::where('name', 'Administrador')->value('id');
        $u->save();
        $c = Customer::create(['name' => 'María Torres', 'identification' => '0912345678']);
        $p = Product::create(['sku' => 'POS-1', 'name' => 'Teclado', 'category_id' => Category::create(['name' => 'Tecnología'])->id, 'purchase_price' => '4.00', 'sale_price' => '10.00', 'tax_basis_points' => 1500]);
        app(InventoryService::class)->adjust($p->id, 5, 'Stock inicial', $u);

        return [$u, $c, $p];
    }

    private function payload($c, $p): array
    {
        return ['customer_id' => $c->id, 'idempotency_key' => (string) \Str::uuid(), 'items' => [['product_id' => $p->id, 'quantity' => 2]], 'discount' => '0.01', 'method' => 'cash', 'tendered' => '30.00'];
    }

    public function test_sale_is_exact_idempotent_and_ignores_client_price(): void
    {
        [$u,$c,$p] = $this->fixture();
        $data = $this->payload($c, $p);
        $data['items'][0]['unit_price'] = '0.01';
        $sale = app(SaleService::class)->confirm($data, $u);
        $again = app(SaleService::class)->confirm($data, $u);
        $this->assertSame($sale->id, $again->id);
        $this->assertSame('22.99', $sale->total);
        $this->assertSame('7.01', $sale->payments->first()->change);
        $this->assertSame(3, $p->fresh()->stock);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->actingAs($u)->get('/sales/'.$sale->id)->assertOk();
    }

    public function test_insufficient_stock_rolls_back_sale_and_payments(): void
    {
        [$u,$c,$p] = $this->fixture();
        $data = $this->payload($c, $p);
        $data['items'][0]['quantity'] = 6;
        try {
            app(SaleService::class)->confirm($data, $u);
            $this->fail('Se permitió sobreventa');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }$this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $p->fresh()->stock);
    }

    public function test_cancellation_restores_stock_once_and_preserves_document(): void
    {
        [$u,$c,$p] = $this->fixture();
        $service = app(SaleService::class);
        $sale = $service->confirm($this->payload($c, $p), $u);
        $service->cancel($sale->id, 'Cliente solicita anulación', $u);
        $service->cancel($sale->id, 'Reintento de anulación', $u);
        $this->assertSame(5, $p->fresh()->stock);
        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseCount('inventory_movements', 3);
    }

    public function test_seller_cannot_authorize_discount_or_cancel(): void
    {
        [$u,$c,$p] = $this->fixture();
        $u->role_id = Role::where('name', 'Vendedor')->value('id');
        $u->save();
        $u->unsetRelation('role');
        $this->actingAs($u)->post('/sales', $this->payload($c, $p))->assertSessionHasErrors('discount');
    }

    public function test_insufficient_payment_rolls_back_everything(): void
    {
        [$u,$c,$p] = $this->fixture();
        $data = $this->payload($c, $p);
        $data['tendered'] = '1.00';
        $this->actingAs($u)->post('/sales', $data)->assertSessionHasErrors('tendered');
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $p->fresh()->stock);
    }

    public function test_point_of_sale_works_with_livewire(): void
    {
        [$u,$c,$p] = $this->fixture();
        $this->actingAs($u)->get('/sales/create')->assertOk()->assertSee('Teclado');
        Livewire::test(PointOfSale::class)->call('addProduct', $p->id)->set('customerId', (string) $c->id)->set('tendered', '20.00')->call('confirm')->assertHasNoErrors();
        $this->assertDatabaseCount('sales', 1);
    }
}
