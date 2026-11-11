<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class PaymentGatewayOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('spmb.manage');
    }

    public function rules(): array
    {
        return ['search' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|in:gateway_name,id', 'sort_order' => 'nullable|in:asc,desc'];
    }
}
