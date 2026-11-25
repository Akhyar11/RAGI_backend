<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class RejectPengajuanKasRequest extends FormRequest
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
            'catatan' => 'nullable|string|max:500',
        ];
    }
}
