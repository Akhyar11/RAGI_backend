<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StoreUmkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->isSuperAdmin() || $user->isAdmin() || $user->hasPermission('sikeu.kaskecil.pengajuan')));
    }

    public function rules(): array
    {
        return [
            'unit_kas_id' => 'nullable|integer|exists:sikeu_unit_kas,id',
            'nama_penerima' => 'required|string|min:3|max:191',
            'tanggal' => 'required|date|before_or_equal:today',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'keterangan' => 'nullable|string|max:2000',
            'total' => 'required|numeric|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_kas_id.exists' => 'Unit kas tidak valid.',
            'nama_penerima.required' => 'Nama penerima UMK wajib diisi.',
            'tanggal.before_or_equal' => 'Tanggal UMK tidak boleh di masa depan.',
            'total.min' => 'Total UMK harus lebih dari nol.',
        ];
    }
}
