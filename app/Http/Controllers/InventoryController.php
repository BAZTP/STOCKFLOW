<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    public function index(Request $r)
    {
        Gate::authorize('inventory.view');
        $r->validate(['product_id' => 'nullable|integer|exists:products,id']);
        $query = InventoryMovement::with('product', 'user')->latest();
        if ($r->filled('product_id')) {
            $query->where('product_id', $r->product_id);
        }

        return view('inventory.index', ['movements' => $query->paginate(20)->withQueryString(), 'products' => Product::orderBy('name')->get()]);
    }

    public function store(Request $r, InventoryService $service)
    {
        Gate::authorize('inventory.manage');
        $data = $r->validate(['product_id' => 'required|exists:products,id', 'quantity' => 'required|integer|not_in:0|between:-1000000,1000000', 'reason' => 'required|string|min:3|max:255']);
        $service->adjust((int) $data['product_id'], (int) $data['quantity'], $data['reason'], $r->user());

        return back()->with('status', 'Ajuste registrado con trazabilidad.');
    }
}
