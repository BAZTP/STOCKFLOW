<?php

namespace App\Services;

use App\Http\Requests\SaleRequest;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private InventoryService $inventory) {}

    public function confirm(array $data, User $user): Sale
    {
        Gate::forUser($user)->authorize('sales.manage');
        Validator::make($data, (new SaleRequest)->rules())->validate();
        if (Money::cents($data['discount'] ?? '0') > 0 && $user->role?->name !== 'Administrador') {
            throw ValidationException::withMessages(['discount' => 'Sólo un administrador puede autorizar descuentos.']);
        }
        try {
            return DB::transaction(function () use ($data, $user) {
                if ($existing = Sale::where('idempotency_key', $data['idempotency_key'])->first()) {
                    abort_unless($existing->user_id === $user->id, 403);

                    return $existing;
                }
                $customer = Customer::findOrFail($data['customer_id']);
                if (! $customer->active) {
                    throw ValidationException::withMessages(['customer_id' => 'El cliente está inactivo.']);
                }
                $sale = Sale::create(['customer_id' => $customer->id, 'user_id' => $user->id, 'idempotency_key' => $data['idempotency_key']]);
                // Lock every product in the same order for all sales, purchases and reversals.
                $products = Product::whereIn('id', array_column($data['items'], 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $remainingDiscount = Money::cents($data['discount'] ?? '0');
                $subtotal = 0;
                $discount = 0;
                $tax = 0;
                $cost = 0;
                foreach ($data['items'] as $input) {
                    $product = $products->get($input['product_id']);
                    $quantity = (int) $input['quantity'];
                    if (! $product || ! $product->active) {
                        throw ValidationException::withMessages(['items' => 'El producto no está disponible.']);
                    }if ($product->stock < $quantity) {
                        throw ValidationException::withMessages(['stock' => "Stock insuficiente para {$product->name}."]);
                    }
                    $price = Money::cents($product->sale_price);
                    $lineDiscount = min($remainingDiscount, $price * $quantity);
                    $remainingDiscount -= $lineDiscount;
                    $line = Money::line($price, $quantity, $lineDiscount, $product->tax_basis_points);
                    $unitCost = Money::cents($product->purchase_price);
                    $sale->items()->create(['product_id' => $product->id, 'description' => $product->name, 'category_name' => $product->category?->name ?? 'Sin categoría', 'quantity' => $quantity, 'unit_price' => Money::decimal($price), 'unit_cost' => Money::decimal($unitCost), 'tax_basis_points' => $product->tax_basis_points, 'discount' => Money::decimal($lineDiscount), 'tax' => Money::decimal($line['tax']), 'total' => Money::decimal($line['total'])]);
                    $this->inventory->applyLocked($product, -$quantity, 'sale', 'Venta #'.$sale->id, $user->id, 'sale', $sale->id);
                    $subtotal += $line['gross'];
                    $discount += $lineDiscount;
                    $tax += $line['tax'];
                    $cost += $unitCost * $quantity;
                }
                if ($remainingDiscount > 0) {
                    throw ValidationException::withMessages(['discount' => 'El descuento supera el subtotal.']);
                }$total = $subtotal - $discount + $tax;
                Money::bounded($total);
                Money::bounded($cost);
                $tendered = Money::cents($data['tendered']);
                if ($tendered < $total) {
                    throw ValidationException::withMessages(['tendered' => 'El pago no cubre el total de la venta.']);
                }if ($data['method'] !== 'cash' && $tendered !== $total) {
                    throw ValidationException::withMessages(['tendered' => 'Tarjeta y transferencia requieren el importe exacto.']);
                }
                $sale->update(['status' => 'completed', 'subtotal' => Money::decimal($subtotal), 'discount' => Money::decimal($discount), 'tax' => Money::decimal($tax), 'total' => Money::decimal($total), 'cost' => Money::decimal($cost)]);
                $sale->payments()->create(['method' => $data['method'], 'amount' => Money::decimal($total), 'tendered' => Money::decimal($tendered), 'change' => Money::decimal($tendered - $total)]);
                $company = CompanySetting::findOrFail(1);
                $sale->document()->create(['number' => $company->document_prefix.'-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT), 'company_snapshot' => $company->only(['name', 'legal_name', 'identification', 'address', 'phone', 'email', 'currency', 'logo_path']), 'customer_snapshot' => $customer->only(['name', 'identification', 'address', 'phone', 'email'])]);
                AuditLog::create(['user_id' => $user->id, 'action' => 'sale.confirmed', 'subject_type' => 'sales', 'subject_id' => $sale->id]);

                return $sale;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $sale = Sale::where('idempotency_key', $data['idempotency_key'])->where('user_id', $user->id)->first();
            if ($sale) {
                return $sale;
            }throw $e;
        }
    }

    public function cancel(int $id, string $reason, User $user): Sale
    {
        abort_unless($user->active && $user->role?->name === 'Administrador', 403);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:255'])->validate();

        return DB::transaction(function () use ($id, $reason, $user) {
            $sale = Sale::lockForUpdate()->findOrFail($id);
            if ($sale->status === 'cancelled') {
                return $sale;
            }if ($sale->status !== 'completed') {
                throw ValidationException::withMessages(['sale' => 'Sólo se pueden anular ventas confirmadas.']);
            }
            foreach ($sale->items()->orderBy('product_id')->get() as $item) {
                $product = Product::withTrashed()->lockForUpdate()->findOrFail($item->product_id);
                $this->inventory->applyLocked($product, $item->quantity, 'reversal', 'Anulación #'.$sale->id.': '.$reason, $user->id, 'sale', $sale->id);
            }
            $sale->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);
            AuditLog::create(['user_id' => $user->id, 'action' => 'sale.cancelled', 'subject_type' => 'sales', 'subject_id' => $sale->id, 'details' => ['reason' => $reason]]);

            return $sale;
        }, 3);
    }
}
