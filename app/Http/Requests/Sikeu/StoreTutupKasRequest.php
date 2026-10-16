<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StoreTutupKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->isSuperAdmin() || $user->isAdmin() || $user->hasPermission('sikeu.kas.manage')));
    }

    public function rules(): array
    {
        return [
            'unit_kas_id' => 'required|integer|exists:sikeu_unit_kas,id',
            'tanggal' => 'required|date|before_or_equal:today',
            'saldo_awal' => 'nullable|numeric|min:0',
            'saldo_masuk' => 'nullable|numeric|min:0',
            'saldo_keluar' => 'nullable|numeric|min:0',
            'saldo_akhir' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_kas_id.exists' => 'Unit kas tidak valid.',
            'tanggal.before_or_equal' => 'Tanggal tutup kas tidak boleh di masa depan.',
        ];
    }
}
