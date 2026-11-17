<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class IssueSpmbRegistrationInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('kode_diskon')) {
            $this->merge(['kode_diskon' => Str::upper(trim((string) $this->input('kode_diskon')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['kode_diskon' => ['sometimes', 'nullable', 'string', 'max:50']];
    }
}