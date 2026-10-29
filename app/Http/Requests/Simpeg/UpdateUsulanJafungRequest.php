<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $usulanId = $this->route('usulan_jafung')?->id ?? $this->route('usulan_jafung');
        $pegawaiId = $this->pegawai_id ?? $this->route('usulan_jafung')?->pegawai_id;

        return [
            'pegawai_id' => 'sometimes|required|exists:simpeg_pegawai,id',
            'jafung_asal_id' => 'nullable|exists:simpeg_jabatan_fungsional_akademik,id',
            'jafung_tujuan_id' => [
                'sometimes',
                'required',
                'exists:simpeg_jabatan_fungsional_akademik,id',
                Rule::unique('simpeg_usulan_jafung', 'jafung_tujuan_id')
                    ->where(fn ($query) => $query->where('pegawai_id', $pegawaiId))
                    ->ignore($usulanId),
            ],
            'nomor_sk' => 'sometimes|required|string|max:150',
            'tanggal_mulai_berlaku' => 'sometimes|required|date',
            'angka_kredit_usulan' => 'sometimes|nullable|integer|min:0',
            'status_usulan' => 'nullable|in:disetujui,ditolak',
            'catatan_reviewer' => 'nullable|string',
            'file_sk_hasil' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.exists' => 'Pegawai tidak valid.',
            'jafung_tujuan_id.exists' => 'Jabatan fungsional tujuan tidak valid.',
            'jafung_tujuan_id.unique' => 'Jabatan Akademik ini sudah pernah tercatat untuk dosen yang bersangkutan.',
            'file_sk_hasil.max' => 'Ukuran file SK maksimal 10MB.',
        ];
    }
}
