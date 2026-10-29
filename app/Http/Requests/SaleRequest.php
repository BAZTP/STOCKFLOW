<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->permits('sales.manage');
    }

    public function rules(): array
    {
        return ['customer_id' => 'required|integer|exists:customers,id', 'idempotency_key' => 'required|uuid', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|integer|distinct|exists:products,id', 'items.*.quantity' => 'required|integer|min:1|max:100000', 'discount' => 'nullable|numeric|decimal:0,2|min:0|max:999999999.99', 'method' => 'required|in:cash,transfer,card', 'tendered' => 'required|numeric|decimal:0,2|min:0|max:999999999999.99'];
    }
}
