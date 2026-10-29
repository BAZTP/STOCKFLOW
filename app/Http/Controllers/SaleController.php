<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    public function index()
    {
        Gate::authorize('sales.view');

        return view('sales.index', ['sales' => Sale::with('customer', 'user')->latest()->paginate(20)]);
    }

    public function create()
    {
        Gate::authorize('sales.manage');

        return view('sales.create');
    }

    public function store(SaleRequest $r, SaleService $service)
    {
        $sale = $service->confirm($r->validated(), $r->user());

        return redirect()->route('sales.show', $sale);
    }

    public function show(Sale $sale)
    {
        Gate::authorize('sales.view');
        $sale->load('items', 'payments', 'customer', 'document', 'user');

        return view('sales.show', compact('sale'));
    }

    public function cancel(Sale $sale, Request $r, SaleService $service)
    {
        $r->validate(['reason' => 'required|string|min:5|max:255']);
        $service->cancel($sale->id, $r->reason, $r->user());

        return back()->with('status', 'Venta anulada. Stock restituido; el pago se conserva como histórico y debe reembolsarse según el procedimiento de la empresa.');
    }
}
