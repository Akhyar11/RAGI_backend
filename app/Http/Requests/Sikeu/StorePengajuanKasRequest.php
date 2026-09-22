<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StorePengajuanKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sikeu.pengajuan_kas.create') ||
               $this->user()?->hasPermission('sikeu.pengajuan_kas.manage') ||
               $this->user()?->hasRole('superadmin') ||
               $this->user()?->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'unit_kas_id' => 'required|exists:sikeu_unit_kas,id',
            'judul_pengajuan' => 'required|string',
            'deskripsi' => 'nullable|string',
            'nominal_diajukan' => 'required|numeric|min:1',
        ];
    }
}
