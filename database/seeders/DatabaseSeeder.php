<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $map = ['Administrador' => ['*'], 'Vendedor' => ['products.view', 'customers.view', 'customers.manage', 'sales.view', 'sales.manage', 'documents.view'], 'Bodeguero' => ['products.view', 'products.manage', 'suppliers.view', 'suppliers.manage', 'inventory.view', 'inventory.manage', 'purchases.view', 'purchases.manage'], 'Contador' => ['documents.view', 'reports.view', 'sales.view']];
        $all = [];
        foreach (['products', 'suppliers', 'customers', 'purchases', 'inventory', 'sales', 'documents', 'reports', 'settings', 'users'] as $m) {
            foreach (['view', 'manage'] as $a) {
                $all[] = $m.'.'.$a;
            }
        }
        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        foreach ($map as $name => $permissions) {
            $role = Role::firstOrCreate(['name' => $name]);
            $role->permissions()->sync(Permission::whereIn('name', $permissions === ['*'] ? $all : $permissions)->pluck('id'));
        }
        CompanySetting::firstOrCreate(['id' => 1], ['name' => 'StockFlow ERP']);
    }
}
