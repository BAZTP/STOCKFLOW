<?php

namespace App\Providers;

use App\Contracts\DocumentRenderer;
use App\Services\InternalPdfRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentRenderer::class, InternalPdfRenderer::class);
    }

    public function boot(): void
    {
        foreach (['Product', 'Category', 'Supplier', 'Customer', 'User'] as $name) {
            Gate::policy('App\\Models\\'.$name, 'App\\Policies\\'.$name.'Policy');
        }
        foreach (['products', 'suppliers', 'customers', 'purchases', 'inventory', 'sales', 'documents', 'reports', 'settings', 'users'] as $module) {
            foreach (['view', 'manage'] as $action) {
                Gate::define($module.'.'.$action, fn ($u) => $u->permits($module.'.'.$action));
            }
        }
    }
}
