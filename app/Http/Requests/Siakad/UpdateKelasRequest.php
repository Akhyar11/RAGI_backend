<?php

namespace App\Http\Requests\Siakad;

use App\Models\Siakad\Kelas;
use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.kelas.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama_kelas' => 'required|string|max:255',
            'ruangan_id' => 'nullable|exists:sinapra_ruangan,id',
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'team_teaching_dosen_ids' => 'nullable|array',
            'team_teaching_dosen_ids.*' => 'exists:siakad_dosen,id',
            'kapasitas' => 'required|integer|min:1',
            'kuota_krs' => 'required|integer|min:1',
            'hari' => ['required', 'string', new MasterReferensiExists('hari_kuliah')],
            'jam_mulai' => 'required|string',
            'jam_selesai' => 'required|string',
            'is_gabungan' => 'nullable|boolean',
            'gabungan_program_studi_ids' => 'nullable|array',
            'gabungan_program_studi_ids.*' => 'exists:siakad_program_studi,id',
            'parent_kelas_id' => 'nullable|exists:siakad_kelas,id',
            'tipe_sesi' => ['nullable', Rule::in(Kelas::TIPE_SESI)],
            'rombel_ids' => 'nullable|array',
            'rombel_ids.*' => 'exists:siakad_master_kelas,id',
        ];
    }

    public function messages(): array
    {
        return [
            'hari.required' => 'Hari perkuliahan wajib dipilih.',
        ];
    }
}
