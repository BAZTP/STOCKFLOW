<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->permits('settings.manage');
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:180', 'legal_name' => 'nullable|string|max:180', 'identification' => 'nullable|string|max:40', 'address' => 'nullable|string|max:1000', 'phone' => 'nullable|string|max:40', 'email' => 'nullable|email|max:255', 'tax_basis_points' => 'required|integer|between:0,10000', 'minimum_stock' => 'required|integer|between:0,1000000', 'document_prefix' => 'required|regex:/^[A-Z0-9-]{1,12}$/', 'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'];
    }
}
