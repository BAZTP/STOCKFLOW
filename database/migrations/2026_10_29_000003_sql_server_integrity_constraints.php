<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function constraints(): array
    {
        return [
            'products' => [
                'products_nonnegative' => 'stock >= 0 AND minimum_stock >= 0 AND purchase_price >= 0 AND sale_price >= 0',
                'products_tax_range' => 'tax_basis_points BETWEEN 0 AND 10000',
            ],
            'inventory_movements' => ['inventory_nonnegative' => 'stock_before >= 0 AND stock_after >= 0'],
            'sale_items' => ['sale_items_valid' => 'quantity > 0 AND unit_price >= 0 AND unit_cost >= 0 AND discount >= 0 AND tax >= 0 AND total >= 0 AND tax_basis_points BETWEEN 0 AND 10000'],
            'purchase_items' => ['purchase_items_valid' => 'quantity > 0 AND unit_price >= 0 AND unit_cost >= 0 AND discount >= 0 AND tax >= 0 AND total >= 0 AND tax_basis_points BETWEEN 0 AND 10000'],
            'documents' => ['documents_json_valid' => 'ISJSON(company_snapshot) = 1 AND ISJSON(customer_snapshot) = 1'],
            'audit_logs' => ['audit_json_valid' => 'details IS NULL OR ISJSON(details) = 1'],
        ];
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }
        foreach ($this->constraints() as $table => $constraints) {
            foreach ($constraints as $name => $expression) {
                DB::statement("ALTER TABLE [$table] ADD CONSTRAINT [$name] CHECK ($expression)");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }
        foreach ($this->constraints() as $table => $constraints) {
            foreach ($constraints as $name => $expression) {
                DB::statement("ALTER TABLE [$table] DROP CONSTRAINT [$name]");
            }
        }
    }
};
