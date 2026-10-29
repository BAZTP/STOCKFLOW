<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $role = 'Administrador'): User
    {
        $u = User::factory()->create();
        $u->role_id = Role::where('name', $role)->value('id');
        $u->save();

        return $u;
    }

    public function test_pdf_is_real_and_preserves_company_and_customer_snapshots(): void
    {
        $u = $this->user();
        CompanySetting::find(1)->update(['name' => 'Empresa original']);
        $customer = Customer::create(['name' => 'Cliente original', 'identification' => 'DOC-1']);
        $product = Product::create(['sku' => 'DOC-1', 'name' => 'Producto PDF', 'category_id' => Category::create(['name' => 'General'])->id, 'purchase_price' => '1.00', 'sale_price' => '5.00', 'tax_basis_points' => 1500]);
        app(InventoryService::class)->adjust($product->id, 3, 'Inicial', $u);
        $sale = app(SaleService::class)->confirm(['customer_id' => $customer->id, 'idempotency_key' => (string) \Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1]], 'method' => 'cash', 'tendered' => '10.00'], $u);
        CompanySetting::find(1)->update(['name' => 'Empresa nueva']);
        $customer->update(['name' => 'Cliente nuevo']);
        $this->actingAs($u)->get('/documents/'.$sale->document->id)->assertOk()->assertSee('Empresa original')->assertSee('Cliente original')->assertSee('No es una factura electrónica autorizada por el SRI');
        $response = $this->get('/documents/'.$sale->document->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }

    public function test_warehouse_cannot_download_documents_or_change_company(): void
    {
        $u = $this->user('Bodeguero');
        $this->actingAs($u)->get('/documents')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->put('/settings', [])->assertForbidden();
    }

    public function test_admin_can_update_company_and_default_tax(): void
    {
        $u = $this->user();
        $this->actingAs($u)->get('/settings')->assertOk();
        $this->put('/settings', ['name' => 'Mi negocio', 'tax_basis_points' => 1200, 'minimum_stock' => 10, 'document_prefix' => 'INT'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('company_settings', ['id' => 1, 'name' => 'Mi negocio', 'tax_basis_points' => 1200]);
    }
}
