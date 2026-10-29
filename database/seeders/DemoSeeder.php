<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Los datos demo no se pueden cargar en producción.');
        }
        if (AuditLog::where('action', 'demo.seeded')->exists()) {
            return;
        }
        DB::transaction(function () {
            if (User::where('email', 'demo.operator@stockflow.invalid')->exists() || Product::where('sku', 'like', 'DEMO-%')->exists()) {
                throw new \RuntimeException('Existen registros DEMO sin marcador de carga. Se conservaron sin cambios.');
            }
            $this->call(DatabaseSeeder::class);
            $operator = new User(['name' => 'Operador de datos demo', 'email' => 'demo.operator@stockflow.invalid', 'password' => bin2hex(random_bytes(32))]);
            $operator->role_id = Role::where('name', 'Administrador')->value('id');
            $operator->save();
            $suppliers = [];
            foreach (['Andes Tecnología (demo)', 'Distribuidora Pacífico (demo)', 'Suministros del Ecuador (demo)'] as $i => $name) {
                $suppliers[] = Supplier::create(['name' => $name, 'legal_name' => $name, 'identification' => 'DEMO-S-'.($i + 1), 'email' => 'proveedor'.($i + 1).'@example.invalid', 'phone' => '04 200 000'.($i + 1), 'address' => 'Guayaquil, Ecuador · Datos ficticios', 'contact' => 'Contacto comercial']);
            }
            $customers = [];
            foreach (['María Torres', 'Carlos Mendoza', 'Ana Salazar', 'Diego Pérez', 'Lucía Vera', 'Juan Andrade', 'Sofía Castro', 'Pedro Molina'] as $i => $name) {
                $customers[] = Customer::create(['name' => $name.' (demo)', 'identification' => 'DEMO-C-'.($i + 1), 'email' => 'cliente'.($i + 1).'@example.invalid', 'phone' => '09 0000 000'.($i + 1), 'address' => 'Guayaquil, Ecuador · Datos ficticios']);
            }
            $catalog = ['Tecnología' => ['Teclado mecánico', 'Mouse inalámbrico', 'Monitor 24 pulgadas', 'Hub USB-C', 'Audífonos Bluetooth', 'Webcam Full HD', 'Disco SSD 500 GB', 'Memoria USB 64 GB'], 'Oficina' => ['Silla ergonómica', 'Soporte para portátil', 'Lámpara de escritorio', 'Organizador de documentos', 'Calculadora de escritorio', 'Pizarra magnética'], 'Papelería' => ['Resma de papel A4', 'Cuaderno ejecutivo', 'Bolígrafos azules x12', 'Carpeta archivadora', 'Notas adhesivas', 'Marcadores de colores']];
            $products = [];
            $index = 0;
            foreach ($catalog as $category => $names) {
                $cat = Category::firstOrCreate(['name' => $category]);
                foreach ($names as $name) {
                    $cost = 500 + (($index * 1733) % 24000);
                    $products[] = Product::create(['sku' => 'DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), 'barcode' => 'DEMO-BAR-'.($index + 1), 'name' => $name, 'description' => 'Producto de demostración para validar el flujo comercial.', 'category_id' => $cat->id, 'supplier_id' => $suppliers[$index % 3]->id, 'purchase_price' => Money::decimal($cost), 'sale_price' => Money::decimal($cost + intdiv($cost * 35, 100)), 'tax_basis_points' => 1500, 'minimum_stock' => 5, 'unit' => 'unidad', 'active' => true]);
                    $index++;
                }
            }
            foreach ($suppliers as $supplier) {
                $items = [];
                foreach ($products as $i => $product) {
                    if ($product->supplier_id === $supplier->id) {
                        $items[] = ['product_id' => $product->id, 'quantity' => $i >= count($products) - 3 ? 3 : 70, 'unit_price' => $product->purchase_price, 'discount' => '0.00'];
                    }
                }$purchase = app(PurchaseService::class)->create(['supplier_id' => $supplier->id, 'idempotency_key' => (string) \Str::uuid(), 'items' => $items], $operator);
                app(PurchaseService::class)->receive($purchase->id, $operator);
                $purchase->forceFill(['created_at' => now()->subDays(45), 'updated_at' => now()->subDays(44)])->save();
                InventoryMovement::where('reference_type', 'purchase')->where('reference_id', $purchase->id)->update(['created_at' => now()->subDays(44), 'updated_at' => now()->subDays(44)]);
            }
            for ($day = 30; $day >= 0; $day--) {
                for ($j = 0; $j < 2; $j++) {
                    $i = ($day * 2 + $j) % (count($products) - 3);
                    $product = $products[$i];
                    $quantity = 1 + ($day + $j) % 2;
                    $gross = Money::cents($product->sale_price) * $quantity;
                    $total = $gross + Money::tax($gross, $product->tax_basis_points);
                    $sale = app(SaleService::class)->confirm(['customer_id' => $customers[($day + $j) % count($customers)]->id, 'idempotency_key' => (string) \Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => $quantity]], 'discount' => '0.00', 'method' => ['cash', 'transfer', 'card'][($day + $j) % 3], 'tendered' => Money::decimal($total)], $operator);
                    $date = now()->subDays($day)->setTime(10 + $j * 4, 15);
                    $sale->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
                    $sale->items()->update(['created_at' => $date, 'updated_at' => $date]);
                    $sale->payments()->update(['created_at' => $date, 'updated_at' => $date]);
                    $sale->document()->update(['created_at' => $date, 'updated_at' => $date]);
                    InventoryMovement::where('reference_type', 'sale')->where('reference_id', $sale->id)->update(['created_at' => $date, 'updated_at' => $date]);
                }
            }
            $operator->active = false;
            $operator->save();
            AuditLog::create(['user_id' => $operator->id, 'action' => 'demo.seeded', 'subject_type' => 'environment', 'details' => ['products' => count($products), 'sales' => 62]]);
        });
    }
}
