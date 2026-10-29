<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
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
        $c = Customer::create(['name' => 'Cliente reporte', 'identification' => 'REPORT-1']);
        $p = Product::create(['sku' => 'REPORT-1', 'name' => 'Producto reporte', 'category_id' => Category::create(['name' => 'Categoría original'])->id, 'purchase_price' => '4.00', 'sale_price' => '10.00', 'tax_basis_points' => 1500]);
        app(InventoryService::class)->adjust($p->id, 10, 'Inventario inicial', $u);
        $sale = app(SaleService::class)->confirm(['customer_id' => $c->id, 'idempotency_key' => (string) \Str::uuid(), 'items' => [['product_id' => $p->id, 'quantity' => 2]], 'discount' => '1.00', 'method' => 'cash', 'tendered' => '30.00'], $u);

        return [$u, $p, $sale];
    }

    public function test_reports_use_net_income_and_historical_costs(): void
    {
        [$u,$p,$sale] = $this->fixture();
        $p->update(['purchase_price' => '99.00', 'category_id' => Category::create(['name' => 'Nueva categoría'])->id]);
        $service = app(ReportService::class);
        $date = today()->toDateString();
        $summary = $service->summary($date, $date);
        $this->assertSame('19.00', $summary['net']);
        $this->assertSame('8.00', $summary['cost']);
        $this->assertSame('11.00', $summary['profit']);
        $this->assertSame('2.85', $summary['tax']);
        $this->assertSame('Categoría original', $service->charts($date, $date)['categories']['labels']->first());
        app(SaleService::class)->cancel($sale->id, 'Anulación de prueba', $u);
        $this->assertSame('0.00', $service->summary($date, $date)['net']);
    }

    public function test_every_report_and_export_executes(): void
    {
        [$u] = $this->fixture();
        $this->actingAs($u);
        foreach (array_keys(ReportService::types()) as $type) {
            $this->get('/reports?type='.$type)->assertOk();
            $response = $this->get('/reports/export-csv?type='.$type)->assertOk();
            $this->assertNotEmpty($response->streamedContent());
        }$pdf = $this->get('/reports/export-pdf?type=profit')->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_seller_cannot_access_financial_reports(): void
    {
        [$u] = $this->fixture();
        $u->role_id = Role::where('name', 'Vendedor')->value('id');
        $u->save();
        $u->unsetRelation('role');
        $this->actingAs($u)->get('/reports')->assertForbidden();
        $this->get('/reports/export-csv')->assertForbidden();
    }
}
