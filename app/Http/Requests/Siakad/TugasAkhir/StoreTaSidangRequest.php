<?php

namespace App\Http\Requests\Siakad\TugasAkhir;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaSidangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.ta.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'jenis_sidang' => 'required|in:proposal,seminar_hasil,sidang_skripsi',
            'periode_sidang' => 'nullable|string|max:50',
            'tanggal_sidang' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'ruangan_id' => 'nullable|exists:sinapra_ruangan,id',
            'ruangan_nama_manual' => 'nullable|string|max:100',
            'ketua_sidang_id' => 'nullable|exists:siakad_dosen,id',
            'sekretaris_sidang_id' => 'nullable|exists:siakad_dosen,id',
            'catatan_sidang' => 'nullable|string',
            'penguji' => 'required|array|min:1',
            'penguji.*.dosen_id' => 'required|exists:siakad_dosen,id|distinct',
            'penguji.*.peran_penguji' => 'required|in:ketua,sekretaris,penguji_1,penguji_2,penguji_luar',
        ];
    }
}
