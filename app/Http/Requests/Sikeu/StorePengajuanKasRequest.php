<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StorePengajuanKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Slug sikeu.pengajuan_kas.* belum ada di master permission,
        // pakai slug pengajuan operasional yang sudah ada agar petugas unit bisa mengajukan.
        return $this->user()?->hasPermission('sikeu.pengajuan.create') ||
               $this->user()?->hasPermission('sikeu.pengajuan.manage') ||
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
