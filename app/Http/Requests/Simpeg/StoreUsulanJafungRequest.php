<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsulanJafungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('simpeg.usulan_jafung.create') ||
               $this->user()?->hasPermission('simpeg.usulan_jafung.request');
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'jafung_asal_id' => 'nullable|exists:simpeg_jabatan_fungsional_akademik,id',
            'jafung_tujuan_id' => [
                'required',
                'exists:simpeg_jabatan_fungsional_akademik,id',
                Rule::unique('simpeg_usulan_jafung', 'jafung_tujuan_id')
                    ->where(fn ($query) => $query->where('pegawai_id', $this->pegawai_id)),
            ],
            'nomor_sk' => 'required|string|max:150',
            'tanggal_mulai_berlaku' => 'required|date',
            'angka_kredit_usulan' => 'nullable|integer|min:0',
            'catatan_reviewer' => 'nullable|string',
            'file_sk_hasil' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Pegawai/Dosen wajib dipilih.',
            'pegawai_id.exists' => 'Pegawai tidak valid.',
            'jafung_tujuan_id.required' => 'Jabatan Akademik (JAD) wajib dipilih.',
            'jafung_tujuan_id.exists' => 'Jabatan Akademik tidak valid.',
            'jafung_tujuan_id.unique' => 'Jabatan Akademik ini sudah pernah tercatat untuk dosen yang bersangkutan.',
            'nomor_sk.required' => 'Nomor SK penetapan wajib diisi.',
            'tanggal_mulai_berlaku.required' => 'Tanggal mulai berlaku (TMT) wajib diisi.',
            'tanggal_mulai_berlaku.date' => 'Format tanggal mulai berlaku tidak valid.',
            'file_sk_hasil.max' => 'Ukuran file SK maksimal 10MB.',
        ];
    }
}
