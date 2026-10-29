<?php

namespace App\Http\Requests;

use App\Support\Catalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $def = Catalog::definition($this->route('resource'));
        $id = $this->route('id');

        return $id ? Gate::allows('update', $def['model']::findOrFail($id)) : Gate::allows('create', $def['model']);
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('resource') !== 'categories') {
            $this->merge(['active' => $this->boolean('active')]);
        }
    }

    public function rules(): array
    {
        return Catalog::rules($this->route('resource'), $this->route('id') ? (int) $this->route('id') : null);
    }
}
