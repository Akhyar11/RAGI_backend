<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreKelasKomponenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'nama_komponen' => 'required|string|max:255',
            'bobot' => 'required|numeric|min:1|max:100',
            'cpmk_id' => 'nullable|exists:siakad_cpmk,id',
            'sub_cpmk_id' => 'nullable|exists:siakad_sub_cpmk,id',
            'id' => 'nullable|exists:siakad_komponen_penilaian,id',
        ];

        // Mode full_obe tidak memakai teknik penilaian (nilai per CPMK).
        if ($this->kelasMode() !== 'full_obe') {
            $rules['teknik_penilaian'] = ['required', 'string', new MasterReferensiExists('teknik_penilaian')];
        }

        return $rules;
    }

    private function kelasMode(): string
    {
        $kelasId = $this->route('kelasId');
        if (!$kelasId) {
            return 'semi_obe';
        }
        $kelas = \App\Models\Siakad\Kelas::with('tahunAkademik')->find($kelasId);
        return $kelas?->tahunAkademik?->mode_penilaian ?? 'semi_obe';
    }

    public function messages(): array
    {
        return [
            'teknik_penilaian.required' => 'Teknik penilaian wajib dipilih.',
        ];
    }
}
