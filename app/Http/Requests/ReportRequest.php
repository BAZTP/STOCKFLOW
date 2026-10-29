<?php

namespace App\Http\Requests;

use App\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->permits('reports.view');
    }

    public function rules(): array
    {
        return ['type' => ['nullable', Rule::in(array_keys(ReportService::types()))], 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from'];
    }

    public function range(): array
    {
        return [$this->validated('from') ?? now()->startOfMonth()->toDateString(), $this->validated('to') ?? now()->toDateString()];
    }
}
