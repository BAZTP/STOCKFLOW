<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->permits('purchases.manage');
    }

    public function rules(): array
    {
        return ['supplier_id' => 'required|exists:suppliers,id', 'idempotency_key' => 'required|uuid', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|distinct|exists:products,id', 'items.*.quantity' => 'required|integer|min:1|max:100000', 'items.*.unit_price' => 'required|numeric|decimal:0,2|min:0|max:999999999.99', 'items.*.discount' => 'nullable|numeric|decimal:0,2|min:0|max:999999999.99'];
    }
}
