<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function adjust(int $productId, int $quantity, string $reason, User $user): InventoryMovement
    {
        Gate::forUser($user)->authorize('inventory.manage');
        Validator::make(compact('quantity', 'reason'), ['quantity' => 'required|integer|not_in:0|between:-1000000,1000000', 'reason' => 'required|string|min:3|max:255'])->validate();

        return DB::transaction(function () use ($productId, $quantity, $reason, $user) {
            $product = Product::lockForUpdate()->findOrFail($productId);

            return $this->applyLocked($product, $quantity, 'adjustment', $reason, $user->id);
        }, 3);
    }

    public function applyLocked(Product $product, int $delta, string $type, string $reason, int $userId, ?string $referenceType = null, ?int $referenceId = null): InventoryMovement
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Los movimientos requieren una transacción.');
        }
        $before = (int) $product->stock;
        $after = $before + $delta;
        if ($after < 0 || $after > 1000000000) {
            throw ValidationException::withMessages(['stock' => "Stock insuficiente o fuera de límite para {$product->name}."]);
        }
        $product->stock = $after;
        $product->save();
        $movement = InventoryMovement::create(['product_id' => $product->id, 'user_id' => $userId, 'type' => $type, 'quantity' => $delta, 'stock_before' => $before, 'stock_after' => $after, 'reason' => $reason, 'reference_type' => $referenceType, 'reference_id' => $referenceId]);
        AuditLog::create(['user_id' => $userId, 'action' => 'inventory.'.$type, 'subject_type' => 'products', 'subject_id' => $product->id, 'details' => ['before' => $before, 'after' => $after, 'movement_id' => $movement->id]]);

        return $movement;
    }
}
