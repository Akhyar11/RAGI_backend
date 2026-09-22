<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsulanJafungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('simpeg.usulan_jafung.update') ||
               $this->user()?->hasPermission('simpeg.usulan_jafung.verify') ||
               $this->user()?->hasPermission('simpeg.usulan_jafung.request');
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'sometimes|required|exists:simpeg_pegawai,id',
            'jafung_asal_id' => 'nullable|exists:simpeg_jabatan_fungsional_akademik,id',
            'jafung_tujuan_id' => 'sometimes|required|exists:simpeg_jabatan_fungsional_akademik,id',
            'angka_kredit_usulan' => 'sometimes|required|integer|min:0',
            'status_usulan' => 'nullable|in:disetujui,ditolak',
            'catatan_reviewer' => 'nullable|string',
            'file_sk_hasil' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.exists' => 'Pegawai tidak valid.',
            'jafung_tujuan_id.exists' => 'Jabatan fungsional tujuan tidak valid.',
            'file_sk_hasil.max' => 'Ukuran file SK maksimal 5MB.',
        ];
    }
}
