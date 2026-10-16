<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUmkRequest extends FormRequest
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
            'nama_penerima' => 'sometimes|required|string|min:3|max:191',
            'tanggal' => 'sometimes|required|date|before_or_equal:today',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'keterangan' => 'nullable|string|max:2000',
            'total' => 'sometimes|required|numeric|min:1',
        ];
    }
}
