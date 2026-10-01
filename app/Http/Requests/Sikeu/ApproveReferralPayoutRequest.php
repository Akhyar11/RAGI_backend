<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class ApproveReferralPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('sikeu.pengeluaran.read');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'aksi' => ['required', 'in:approve,reject'],
            'catatan' => ['nullable', 'string', 'max:1000', 'required_if:aksi,reject'],
        ];
    }
}
