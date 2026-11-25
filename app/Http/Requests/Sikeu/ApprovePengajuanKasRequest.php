<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePengajuanKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && (
            $user->isSuperAdmin() ||
            $user->isAdmin() ||
            $user->hasPermission('sikeu.pengajuan.approve') ||
            $user->hasPermission('sikeu.kas.manage')
        );
    }

    public function rules(): array
    {
        return [
            'nominal_disetujui' => 'nullable|numeric|min:0',
            'unit_kas_id' => 'nullable|exists:sikeu_unit_kas,id',
        ];
    }
}
