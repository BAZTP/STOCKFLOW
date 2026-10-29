<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Product;
use App\Services\SaleService;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PointOfSale extends Component
{
    public string $search = '';

    public string $customerId = '';

    public string $method = 'cash';

    public string $tendered = '';

    public string $discount = '0.00';

    #[Locked]
    public array $cart = [];

    #[Locked]
    public string $idempotencyKey;

    public function mount()
    {
        Gate::authorize('sales.manage');
        $this->idempotencyKey = (string) \Str::uuid();
    }

    public function addProduct(int $id): void
    {
        Gate::authorize('sales.manage');
        $product = Product::where('active', true)->findOrFail($id);
        $quantity = ($this->cart[$id] ?? 0) + 1;
        if ($quantity > $product->stock) {
            throw ValidationException::withMessages(['stock' => 'No hay más unidades disponibles.']);
        }if (count($this->cart) >= 100 && ! isset($this->cart[$id])) {
            throw ValidationException::withMessages(['cart' => 'Máximo 100 productos por venta.']);
        }$this->cart[$id] = $quantity;
    }

    public function scan(): void
    {
        Gate::authorize('sales.manage');
        $product = Product::where('active', true)->where(fn ($q) => $q->where('barcode', $this->search)->orWhere('sku', $this->search))->first();
        if (! $product) {
            throw ValidationException::withMessages(['search' => 'No se encontró ese código de producto.']);
        }$this->addProduct($product->id);
        $this->search = '';
    }

    public function setQuantity(int $id, $quantity): void
    {
        Gate::authorize('sales.manage');
        Validator::make(compact('quantity'), ['quantity' => 'required|integer|min:1|max:100000'])->validate();
        if (! isset($this->cart[$id])) {
            return;
        }$product = Product::findOrFail($id);
        if ((int) $quantity > $product->stock) {
            throw ValidationException::withMessages(['stock' => 'La cantidad supera el stock disponible.']);
        }$this->cart[$id] = (int) $quantity;
    }

    public function removeProduct(int $id): void
    {
        Gate::authorize('sales.manage');
        unset($this->cart[$id]);
    }

    public function confirm(SaleService $service)
    {
        Gate::authorize('sales.manage');
        $items = [];
        foreach ($this->cart as $id => $quantity) {
            $items[] = ['product_id' => $id, 'quantity' => $quantity];
        }$sale = $service->confirm(['customer_id' => $this->customerId, 'idempotency_key' => $this->idempotencyKey, 'items' => $items, 'discount' => $this->discount, 'method' => $this->method, 'tendered' => $this->tendered], auth()->user());
        session()->flash('status', 'Venta confirmada. El inventario y el pago quedaron registrados.');

        return $this->redirectRoute('sales.show', $sale);
    }

    public function render()
    {
        Gate::authorize('sales.manage');
        $products = Product::with('category')->where('active', true)->where(function ($q) {
            $term = '%'.mb_substr($this->search, 0, 120).'%';
            $q->where('name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('barcode', 'like', $term);
        })->orderBy('name')->limit(24)->get();
        $cartProducts = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');
        $lines = [];
        $subtotal = 0;
        $tax = 0;
        $discount = 0;
        try {
            $remaining = Money::cents($this->discount ?: '0');
        } catch (ValidationException) {
            $remaining = 0;
        }
        foreach ($this->cart as $id => $quantity) {
            $p = $cartProducts->get($id);
            if (! $p) {
                continue;
            }$lineDiscount = min($remaining, Money::cents($p->sale_price) * $quantity);
            $remaining -= $lineDiscount;
            $line = Money::line(Money::cents($p->sale_price), $quantity, $lineDiscount, $p->tax_basis_points);
            $subtotal += $line['gross'];
            $tax += $line['tax'];
            $discount += $lineDiscount;
            $lines[] = ['product' => $p, 'quantity' => $quantity, 'total' => Money::decimal($line['total'])];
        }
        $total = $subtotal - $discount + $tax;
        $change = 0;
        try {
            $change = max(0, Money::cents($this->tendered ?: '0') - $total);
        } catch (ValidationException) {
        }

        return view('livewire.point-of-sale', ['products' => $products, 'customers' => Customer::where('active', true)->orderBy('name')->get(), 'lines' => $lines, 'summary' => ['subtotal' => Money::decimal($subtotal), 'discount' => Money::decimal($discount), 'tax' => Money::decimal($tax), 'total' => Money::decimal($total), 'change' => Money::decimal($change)]]);
    }
}
