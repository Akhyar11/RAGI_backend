<?php

namespace App\Http\Requests\IAM;

use App\Services\IAM\SpmbAdminRecipientService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpmbAdminOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('iam.settings.update');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'sort_by' => ['sometimes', Rule::in(SpmbAdminRecipientService::SORTABLE)],
            'sort_order' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
