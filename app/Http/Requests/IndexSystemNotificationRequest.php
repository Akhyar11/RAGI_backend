<?php

namespace App\Http\Requests;

use App\Services\Notifications\SystemNotificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSystemNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('is_read')) {
            $value = filter_var($this->input('is_read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                $this->merge(['is_read' => $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'module_id' => ['nullable', 'integer', 'exists:core_modules,id'],
            'search' => ['nullable', 'string', 'max:200'],
            'is_read' => ['nullable', 'boolean'],
            'sort_by' => ['sometimes', Rule::in(SystemNotificationService::SORTABLE)],
            'sort_order' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
