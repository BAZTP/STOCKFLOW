<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $r)
    {
        $data = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $financial = $r->user()->permits('reports.view');
        $sales = Sale::where('status', 'completed')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $stats = ['products' => Product::where('active', true)->count(), 'customers' => Customer::count(), 'suppliers' => Supplier::count(), 'low' => Product::whereColumn('stock', '<=', 'minimum_stock')->count()];
        if ($financial) {
            $stats += ['today' => Sale::where('status', 'completed')->whereDate('created_at', today())->sum('total'), 'month' => Sale::where('status', 'completed')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'), 'net' => (clone $sales)->selectRaw('COALESCE(SUM(subtotal-discount),0) as value')->value('value'), 'profit' => (clone $sales)->selectRaw('COALESCE(SUM(subtotal-discount-cost),0) as value')->value('value')];
        }
        $daily = $financial ? (clone $sales)->selectRaw('DATE(created_at) as day, SUM(total) as total')->groupBy('day')->orderBy('day')->get() : collect();
        $latest = $r->user()->permits('sales.view') ? (clone $sales)->with('customer', 'user')->latest()->limit(6)->get() : collect();
        $low = Product::with('category')->whereColumn('stock', '<=', 'minimum_stock')->orderBy('stock')->limit(6)->get();
        $charts = $financial ? app(ReportService::class)->charts($from, $to) : [];

        return view('dashboard', compact('stats', 'daily', 'latest', 'low', 'from', 'to', 'financial', 'charts'));
    }
}
