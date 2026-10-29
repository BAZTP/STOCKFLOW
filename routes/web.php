<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\QrLoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// QR login route for mobile scanner (signed relative token)
Route::get('/qr-login/{id}', [QrLoginController::class, 'login'])->name('qr.login')->middleware('signed:relative');

// Auth routes for basic pages
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/qr/generate', [QrLoginController::class, 'show'])->name('qr.generate');
});

// Resource routes (products, categories, etc.)
Route::middleware('auth')->group(function () {
    Route::get('/products/export-csv', [CatalogController::class, 'export'])->name('products.export');
    Route::post('/products/import-csv', [CatalogController::class, 'import'])->name('products.import');
    foreach (['products', 'categories', 'suppliers', 'customers', 'users'] as $resource) {
        foreach ([['GET', '', 'index'], ['GET', '/create', 'create'], ['POST', '', 'store'], ['GET', '/{id}/edit', 'edit'], ['PUT', '/{id}', 'update'], ['DELETE', '/{id}', 'destroy'], ['GET', '/{id}', 'show']] as [$method, $path, $action]) {
            Route::match([$method], '/'.$resource.$path, [CatalogController::class, $action])
                ->defaults('resource', $resource)
                ->whereNumber('id')
                ->name($resource.'.'.$action);
        }
    }
});

// Inventory routes
Route::middleware('auth')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
});

// Sales routes
Route::middleware('auth')->group(function () {
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
});

// Document and settings routes
Route::middleware('auth')->group(function () {
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/pdf', [DocumentController::class, 'pdf'])->name('documents.pdf');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

// Report routes
Route::middleware('auth')->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export-csv', [ReportController::class, 'csv'])->name('reports.csv');
    Route::get('/reports/export-pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
});
