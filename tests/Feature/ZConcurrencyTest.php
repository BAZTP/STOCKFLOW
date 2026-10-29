<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ZConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private function race(bool $sameKey): array
    {
        $this->seed();
        $user = User::factory()->create();
        $user->role_id = Role::where('name', 'Administrador')->value('id');
        $user->save();
        $customer = Customer::create(['name' => 'Cliente concurrente', 'identification' => 'CONC-1']);
        $product = Product::create(['sku' => 'CONC-1', 'name' => 'Última unidad', 'category_id' => Category::create(['name' => 'Pruebas'])->id, 'purchase_price' => '5.00', 'sale_price' => '10.00', 'tax_basis_points' => 1500]);
        app(InventoryService::class)->adjust($product->id, 1, 'Stock para concurrencia', $user);
        $barrier = sys_get_temp_dir().'/stockflow-race-'.bin2hex(random_bytes(8));
        $key = (string) \Str::uuid();
        $processes = [];
        $driver = config('database.default');
        $connection = config('database.connections.'.$driver);
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => $driver, 'DB_DATABASE' => $connection['database'], 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'DB_ENCRYPT' => (string) ($connection['encrypt'] ?? 'yes'), 'DB_TRUST_SERVER_CERTIFICATE' => (string) ($connection['trust_server_certificate'] ?? 'false')];
        for ($i = 0; $i < 2; $i++) {
            $processes[] = new Process([PHP_BINARY, base_path('tests/concurrency-worker.php'), (string) $user->id, (string) $customer->id, (string) $product->id, $sameKey ? $key : (string) \Str::uuid(), $barrier], base_path(), $environment, null, 20);
        }
        try {
            foreach ($processes as $process) {
                $process->start();
            }$codes = [];
            foreach ($processes as $process) {
                $process->wait();
                $codes[] = $process->getExitCode();
                $this->assertContains($process->getExitCode(), [0, 2], $process->getErrorOutput());
            }sort($codes);
            $this->assertSame(0, $product->fresh()->stock);
            $this->assertDatabaseCount('sales', 1);
            $this->assertDatabaseCount('payments', 1);
            $this->assertDatabaseCount('inventory_movements', 2);

            return $codes;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }foreach (glob($barrier.'.*') as $file) {
                unlink($file);
            }
        }
    }

    public function test_two_concurrent_sales_cannot_sell_the_same_last_unit(): void
    {
        $this->assertSame([0, 2], $this->race(false));
    }

    public function test_concurrent_retries_of_same_sale_do_not_duplicate_stock_or_payment(): void
    {
        $this->assertSame([0, 0], $this->race(true));
    }
}
