<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterJenisCutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:100',
            'kode' => 'nullable|string|max:50|unique:simpeg_master_jenis_cuti,kode',
            'tipe_durasi' => 'required|in:ditetapkan,fleksibel',
            'durasi_hari' => 'required_if:tipe_durasi,ditetapkan|nullable|integer|min:0',
            'satuan' => 'nullable|string|max:20',
            'lampiran_wajib' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis izin/cuti wajib diisi.',
            'kode.unique' => 'Kode jenis cuti sudah digunakan.',
            'tipe_durasi.required' => 'Tipe durasi cuti wajib dipilih.',
            'tipe_durasi.in' => 'Tipe durasi cuti harus berupa ditetapkan atau fleksibel.',
            'durasi_hari.required_if' => 'Durasi hari wajib diisi jika tipe durasi ditetapkan.',
            'durasi_hari.min' => 'Durasi hari minimal 0 hari.',
        ];
    }
}
