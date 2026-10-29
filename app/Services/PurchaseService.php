<?php

namespace App\Services;

use App\Http\Requests\PurchaseRequest;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private InventoryService $inventory) {}

    public function create(array $data, User $user): Purchase
    {
        Gate::forUser($user)->authorize('purchases.manage');
        Validator::make($data, (new PurchaseRequest)->rules())->validate();

        return DB::transaction(function () use ($data, $user) {
            $existing = Purchase::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
            $supplier = Supplier::findOrFail($data['supplier_id']);
            if (! $supplier->active) {
                throw ValidationException::withMessages(['supplier_id' => 'El proveedor está inactivo.']);
            }
            $purchase = Purchase::create(['supplier_id' => $supplier->id, 'user_id' => $user->id, 'idempotency_key' => $data['idempotency_key']]);
            $subtotal = 0;
            $discount = 0;
            $tax = 0;
            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                if (! $product->active) {
                    throw ValidationException::withMessages(['items' => 'No se pueden comprar productos inactivos.']);
                }$price = Money::cents($item['unit_price']);
                $line = Money::line($price, (int) $item['quantity'], Money::cents($item['discount'] ?? '0'), $product->tax_basis_points);
                $purchase->items()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => $item['quantity'], 'unit_price' => Money::decimal($price), 'unit_cost' => Money::decimal($price), 'tax_basis_points' => $product->tax_basis_points, 'discount' => Money::decimal($line['discount']), 'tax' => Money::decimal($line['tax']), 'total' => Money::decimal($line['total'])]);
                $subtotal += $line['gross'];
                $discount += $line['discount'];
                $tax += $line['tax'];
            }
            Money::bounded($subtotal - $discount + $tax);
            $purchase->update(['subtotal' => Money::decimal($subtotal), 'discount' => Money::decimal($discount), 'tax' => Money::decimal($tax), 'total' => Money::decimal($subtotal - $discount + $tax), 'cost' => Money::decimal($subtotal - $discount)]);
            AuditLog::create(['user_id' => $user->id, 'action' => 'purchase.created', 'subject_type' => 'purchases', 'subject_id' => $purchase->id]);

            return $purchase;
        }, 3);
    }

    public function receive(int $id, User $user): Purchase
    {
        Gate::forUser($user)->authorize('purchases.manage');

        return DB::transaction(function () use ($id, $user) {
            $purchase = Purchase::lockForUpdate()->findOrFail($id);
            if ($purchase->status === 'received') {
                return $purchase;
            }
            if ($purchase->status !== 'draft') {
                throw ValidationException::withMessages(['purchase' => 'Esta compra no se puede recibir.']);
            }
            foreach ($purchase->items()->orderBy('product_id')->get() as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                if (! $product->active) {
                    throw ValidationException::withMessages(['purchase' => 'No se puede recibir un producto inactivo.']);
                }$net = Money::cents($item->unit_price) * $item->quantity - Money::cents($item->discount);
                $newStock = $product->stock + $item->quantity;
                $weighted = bcadd(bcmul((string) Money::cents($product->purchase_price), (string) $product->stock, 0), (string) $net, 0);
                $product->purchase_price = Money::decimal((int) bcdiv(bcadd($weighted, (string) intdiv($newStock, 2), 0), (string) $newStock, 0));
                $this->inventory->applyLocked($product, $item->quantity, 'purchase', 'Recepción de compra #'.$purchase->id, $user->id, 'purchase', $purchase->id);
            }
            $purchase->update(['status' => 'received']);
            AuditLog::create(['user_id' => $user->id, 'action' => 'purchase.received', 'subject_type' => 'purchases', 'subject_id' => $purchase->id]);

            return $purchase;
        }, 3);
    }
}
