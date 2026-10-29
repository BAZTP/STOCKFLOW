<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->constrained();
            $t->boolean('active')->default(true);
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        foreach (['suppliers', 'customers'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('identification')->unique();
                $t->string('legal_name')->nullable();
                $t->string('email')->nullable();
                $t->string('phone')->nullable();
                $t->text('address')->nullable();
                $t->string('contact')->nullable();
                $t->boolean('active')->default(true);
                $t->timestamps();
                $t->softDeletes();
            });
        }
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('barcode')->nullable();
            if (DB::getDriverName() !== 'sqlsrv') {
                $t->unique('barcode');
            }
            $t->string('name');
            $t->text('description')->nullable();
            $t->foreignId('category_id')->constrained();
            $t->foreignId('supplier_id')->nullable()->constrained();
            $t->decimal('purchase_price', 14, 2);
            $t->decimal('sale_price', 14, 2);
            $t->unsignedInteger('tax_basis_points')->default(1500);
            $t->unsignedInteger('stock')->default(0);
            $t->unsignedInteger('minimum_stock')->default(5);
            $t->string('unit')->default('unidad');
            $t->string('image_path')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX products_barcode_unique ON products (barcode) WHERE barcode IS NOT NULL');
        }
        foreach (['purchases', 'sales'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId($table === 'sales' ? 'customer_id' : 'supplier_id')->constrained();
                $t->foreignId('user_id')->constrained();
                $t->string('idempotency_key', 64)->unique();
                $t->string('status')->default('draft')->index();
                $t->decimal('subtotal', 14, 2)->default(0);
                $t->decimal('discount', 14, 2)->default(0);
                $t->decimal('tax', 14, 2)->default(0);
                $t->decimal('total', 14, 2)->default(0);
                $t->decimal('cost', 14, 2)->default(0);
                $t->text('cancellation_reason')->nullable();
                $t->timestamps();
                $t->index('created_at');
            });
        }
        foreach (['purchase_items', 'sale_items'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId($table === 'sale_items' ? 'sale_id' : 'purchase_id')->constrained();
                $t->foreignId('product_id')->constrained();
                $t->string('description');
                $t->unsignedInteger('quantity');
                $t->decimal('unit_price', 14, 2);
                $t->decimal('unit_cost', 14, 2);
                $t->unsignedInteger('tax_basis_points');
                $t->decimal('discount', 14, 2)->default(0);
                $t->decimal('tax', 14, 2);
                $t->decimal('total', 14, 2);
                $t->timestamps();
            });
        }
        Schema::create('inventory_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('type')->index();
            $t->integer('quantity');
            $t->unsignedInteger('stock_before');
            $t->unsignedInteger('stock_after');
            $t->string('reason');
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->timestamps();
            $t->index(['product_id', 'created_at']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained();
            $t->string('method');
            $t->decimal('amount', 14, 2);
            $t->decimal('tendered', 14, 2);
            $t->decimal('change', 14, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->unique()->constrained();
            $t->string('number')->unique();
            $t->string('type')->default('internal');
            $t->json('company_snapshot');
            $t->json('customer_snapshot');
            $t->timestamps();
        });
        Schema::create('company_settings', function (Blueprint $t) {
            // One settings row has the stable application key 1; no IDENTITY insert needed.
            $t->unsignedBigInteger('id')->primary();
            $t->string('name')->default('StockFlow ERP');
            $t->string('legal_name')->nullable();
            $t->string('identification')->nullable();
            $t->text('address')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('currency')->default('USD');
            $t->string('timezone')->default('America/Guayaquil');
            $t->unsignedInteger('tax_basis_points')->default(1500);
            $t->unsignedInteger('minimum_stock')->default(5);
            $t->string('document_prefix')->default('INT');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('details')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'company_settings', 'documents', 'payments', 'inventory_movements', 'sale_items', 'purchase_items', 'sales', 'purchases', 'products', 'customers', 'suppliers', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('role_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('active'));
        foreach (['permission_role', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
