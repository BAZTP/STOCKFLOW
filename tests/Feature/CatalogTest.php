<?php

namespace Tests\Feature;

use App\Livewire\CatalogTable;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTest extends TestCase
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

    private function product(): array
    {
        return ['sku' => 'SKU-001', 'name' => 'Teclado', 'category_id' => Category::create(['name' => 'Tecnología'])->id, 'purchase_price' => '12.25', 'sale_price' => '20.00', 'tax_basis_points' => 1500, 'minimum_stock' => 5, 'unit' => 'unidad', 'active' => 1];
    }

    public function test_product_crud_and_stock_protection(): void
    {
        $this->actingAs($this->user());
        $data = $this->product();
        $this->post('/products', $data)->assertRedirect('/products');
        $p = Product::firstOrFail();
        $this->assertSame(0, $p->stock);
        $this->put('/products/'.$p->id, [...$data, 'name' => 'Teclado mecánico', 'stock' => 500])->assertSessionHasErrors('stock');
        $this->put('/products/'.$p->id, [...$data, 'name' => 'Teclado mecánico'])->assertRedirect('/products');
        $this->get('/products/'.$p->id)->assertOk()->assertSee('Teclado mecánico');
        $this->delete('/products/'.$p->id)->assertSessionHasNoErrors();
        $this->assertFalse($p->fresh()->active);
    }

    public function test_negative_price_and_duplicate_sku_rejected(): void
    {
        $this->actingAs($this->user());
        $data = $this->product();
        $this->post('/products', [...$data, 'sale_price' => '-0.01'])->assertSessionHasErrors('sale_price');
        $this->post('/products', $data)->assertSessionHasNoErrors();
        $this->post('/products', $data)->assertSessionHasErrors('sku');
    }

    public function test_seller_cannot_mutate_products_or_view_users(): void
    {
        $this->actingAs($this->user('Vendedor'));
        $this->post('/products', $this->product())->assertForbidden();
        $this->get('/products')->assertOk();
        $this->get('/users')->assertForbidden();
    }

    public function test_livewire_search_is_real_and_authorized(): void
    {
        $this->actingAs($this->user());
        Product::create($this->product());
        Livewire::test(CatalogTable::class, ['resource' => 'products'])->set('search', 'Teclado')->assertSee('Teclado')->set('search', 'no-existe')->assertDontSee('SKU-001');
    }

    public function test_csv_import_rolls_back_all_rows_on_error(): void
    {
        $this->actingAs($this->user());
        $category = Category::create(['name' => 'Tecnología']);
        $header = implode(',', Catalog::csvFields());
        $csv = $header."\nSKU-1,,Ratón,,$category->id,,5.00,10.00,1500,5,unidad,1\nSKU-2,,Malo,,$category->id,,-5.00,10.00,1500,5,unidad,1\n";
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);
        $this->post('/products/import-csv', ['csv' => $file])->assertSessionHasErrors('csv');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $u = $this->user();
        $this->actingAs($u)->delete('/users/'.$u->id)->assertSessionHasErrors('active');
        $this->assertTrue($u->fresh()->active);
    }
}
