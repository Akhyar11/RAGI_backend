<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class RejectPengajuanKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sikeu.pengajuan_kas.reject') ||
               $this->user()?->hasPermission('sikeu.pengajuan_kas.approve') ||
               $this->user()?->hasPermission('sikeu.pengajuan_kas.manage');
    }

    public function rules(): array
    {
        return [
            'catatan' => 'nullable|string|max:500',
        ];
    }
}
