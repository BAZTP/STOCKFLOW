<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PurchaseController extends Controller
{
    public function index()
    {
        Gate::authorize('purchases.view');

        return view('purchases.index', ['purchases' => Purchase::with('supplier', 'user')->latest()->paginate(20)]);
    }

    public function create()
    {
        Gate::authorize('purchases.manage');

        return view('purchases.create', ['products' => Product::where('active', true)->orderBy('name')->get(), 'suppliers' => Supplier::where('active', true)->orderBy('name')->get()]);
    }

    public function store(PurchaseRequest $r, PurchaseService $service)
    {
        $purchase = $service->create($r->validated(), $r->user());

        return redirect()->route('purchases.show', $purchase)->with('status', 'Compra registrada como pendiente de recepción.');
    }

    public function show(Purchase $purchase)
    {
        Gate::authorize('purchases.view');
        $purchase->load('items.product', 'supplier', 'user');

        return view('purchases.show', compact('purchase'));
    }

    public function receive(Purchase $purchase, Request $r, PurchaseService $service)
    {
        $service->receive($purchase->id, $r->user());

        return back()->with('status', 'Recepción confirmada. El inventario se actualizó una sola vez.');
    }
}
