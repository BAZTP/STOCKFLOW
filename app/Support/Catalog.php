<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\Rule;

class Catalog
{
    public static function definition(string $resource): array
    {
        $map = [
            'products' => [Product::class, 'Productos', ['sku' => 'SKU', 'barcode' => 'Código de barras', 'name' => 'Nombre', 'description' => 'Descripción', 'category_id' => 'Categoría', 'supplier_id' => 'Proveedor', 'purchase_price' => 'Costo de compra', 'sale_price' => 'Precio de venta', 'tax_basis_points' => 'Impuesto (puntos base: 1500 = 15%)', 'minimum_stock' => 'Stock mínimo', 'unit' => 'Unidad de medida', 'active' => 'Activo']],
            'categories' => [Category::class, 'Categorías', ['name' => 'Nombre']],
            'suppliers' => [Supplier::class, 'Proveedores', ['name' => 'Nombre comercial', 'legal_name' => 'Razón social', 'identification' => 'RUC / identificación', 'email' => 'Correo', 'phone' => 'Teléfono', 'address' => 'Dirección', 'contact' => 'Persona de contacto', 'active' => 'Activo']],
            'customers' => [Customer::class, 'Clientes', ['name' => 'Nombres y apellidos', 'identification' => 'Cédula / RUC / pasaporte', 'email' => 'Correo', 'phone' => 'Teléfono', 'address' => 'Dirección', 'active' => 'Activo']],
            'users' => [User::class, 'Usuarios', ['name' => 'Nombre', 'email' => 'Correo', 'password' => 'Contraseña (mínimo 12 caracteres)', 'role_id' => 'Rol', 'active' => 'Activo']]];
        abort_unless(isset($map[$resource]), 404);
        [$model,$title,$fields] = $map[$resource];

        return compact('model', 'title', 'fields');
    }

    public static function rules(string $resource, ?int $id = null): array
    {
        $unique = fn ($table, $column) => Rule::unique($table, $column)->ignore($id);

        return match ($resource) {
            'products' => ['sku' => ['required', 'string', 'max:80', $unique('products', 'sku')], 'barcode' => ['nullable', 'string', 'max:120', $unique('products', 'barcode')], 'name' => 'required|string|max:180', 'description' => 'nullable|string|max:5000', 'category_id' => 'required|exists:categories,id', 'supplier_id' => 'nullable|exists:suppliers,id', 'purchase_price' => 'required|decimal:0,2|numeric|min:0|max:999999999.99', 'sale_price' => 'required|decimal:0,2|numeric|min:0|max:999999999.99', 'tax_basis_points' => 'required|integer|min:0|max:10000', 'minimum_stock' => 'required|integer|min:0|max:1000000', 'unit' => 'required|string|max:30', 'active' => 'required|boolean', 'stock' => 'prohibited', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'],
            'categories' => ['name' => ['required', 'string', 'max:120', $unique('categories', 'name')]],
            'customers','suppliers' => ['name' => 'required|string|max:180', 'legal_name' => 'nullable|string|max:180', 'identification' => ['required', 'string', 'max:40', $unique($resource, 'identification')], 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:1000', 'contact' => 'nullable|string|max:180', 'active' => 'required|boolean'],
            'users' => ['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:255', $unique('users', 'email')], 'password' => ($id ? 'nullable' : 'required').'|string|min:12|max:255', 'role_id' => 'required|exists:roles,id', 'active' => 'required|boolean'],default => abort(404)
        };
    }

    public static function options(): array
    {
        return ['category_id' => Category::orderBy('name')->pluck('name', 'id'), 'supplier_id' => Supplier::where('active', true)->orderBy('name')->pluck('name', 'id'), 'role_id' => Role::pluck('name', 'id')];
    }

    public static function csvFields(): array
    {
        return ['sku', 'barcode', 'name', 'description', 'category_id', 'supplier_id', 'purchase_price', 'sale_price', 'tax_basis_points', 'minimum_stock', 'unit', 'active'];
    }
}
