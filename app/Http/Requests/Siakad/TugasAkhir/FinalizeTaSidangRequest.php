<?php

namespace App\Http\Requests\Siakad\TugasAkhir;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeTaSidangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.ta.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status_kelulusan' => 'required|in:lulus_tanpa_revisi,lulus_revisi_ringan,lulus_revisi_berat,mengulang',
            'batas_waktu_revisi' => 'nullable|date',
            'catatan_sidang' => 'nullable|string',
            'berkas_bap' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }
}
