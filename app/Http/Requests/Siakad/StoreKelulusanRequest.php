<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreKelulusanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mahasiswa_id' => 'required|exists:siakad_mahasiswa,id',
            'tahun_akademik_id' => 'required|exists:siakad_tahun_akademik,id',
            'tanggal_sidang' => 'nullable|date',
            'ipk_akhir' => 'required|numeric|min:0|max:4',
            'total_sks' => 'required|integer|min:100',
            'masa_studi_semester' => 'required|integer|min:1|max:28',
            'predikat' => ['nullable', 'string', new MasterReferensiExists('predikat_kelulusan')],
            'nomor_ijazah' => 'nullable|string|max:100|unique:siakad_kelulusan,nomor_ijazah',
            'tanggal_ijazah' => 'nullable|date',
        ];
    }
}
