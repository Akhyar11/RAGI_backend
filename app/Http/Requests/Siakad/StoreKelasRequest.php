<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
            'ruangan_id' => 'nullable|exists:sinapra_ruangan,id',
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'team_teaching_dosen_ids' => 'nullable|array',
            'team_teaching_dosen_ids.*' => 'exists:siakad_dosen,id',
            'kode_kelas' => 'required|string|max:20',
            'nama_kelas' => 'required|string|max:255',
            'kapasitas' => 'required|integer|min:1',
            'kuota_krs' => 'required|integer|min:1',
            'hari' => ['required', 'string', new MasterReferensiExists('hari_kuliah')],
            'jam_mulai' => 'required|string',
            'jam_selesai' => 'required|string',
            'is_gabungan' => 'nullable|boolean',
            'gabungan_program_studi_ids' => 'nullable|array',
            'gabungan_program_studi_ids.*' => 'exists:siakad_program_studi,id',
        ];
    }

    public function messages(): array
    {
        return [
            'hari.required' => 'Hari perkuliahan wajib dipilih.',
            'gabungan_program_studi_ids.*.exists' => 'Salah satu prodi gabungan tidak valid.',
        ];
    }
}
