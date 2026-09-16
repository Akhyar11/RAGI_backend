<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreSkpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'tahun' => 'required|integer|min:2000|max:2100',
            'semester' => 'required|in:ganjil,genap,tahunan',
            'pejabat_penilai_id' => 'nullable|exists:simpeg_pegawai,id',
            'items' => 'required|array|min:1',
            'items.*.kategori_skp_id' => 'required|exists:simpeg_master_kategori_skp,id',
            'items.*.uraian_tugas' => 'required|string',
            'items.*.target_output' => 'required|string|max:255',
            'items.*.target_mutu' => 'required|numeric|min:0|max:100',
            'items.*.target_waktu' => 'required|string|max:100',
            'items.*.target_biaya' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Pegawai bersangkutan wajib dipilih.',
            'pegawai_id.exists' => 'Pegawai tidak ditemukan dalam database.',
            'tahun.required' => 'Tahun periode kinerja wajib diisi.',
            'semester.required' => 'Semester periode kinerja wajib dipilih.',
            'items.required' => 'Daftar butir sasaran kinerja (SKP) minimal 1 tugas.',
            'items.min' => 'Daftar butir sasaran kinerja (SKP) minimal 1 tugas.',
            'items.*.kategori_skp_id.required' => 'Kategori butir SKP wajib dipilih.',
            'items.*.kategori_skp_id.exists' => 'Kategori butir SKP tidak valid.',
            'items.*.uraian_tugas.required' => 'Uraian tugas pokok/penunjang wajib diisi.',
            'items.*.target_output.required' => 'Target output/luaran wajib diisi.',
            'items.*.target_mutu.required' => 'Target mutu kualitas (%) wajib diisi.',
            'items.*.target_waktu.required' => 'Target waktu penyelesaian wajib diisi.',
        ];
    }
}
