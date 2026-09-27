<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class VerifyReferralPayoutRequest extends FormRequest
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
        return [];
    }
}
