<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKebijakanPedomanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->isAdmin()
            || $user->hasPermission('simpeg.kebijakan_pedoman.update')
            || $user->hasPermission('simpeg.kebijakan_pedoman.manage');
    }

    public function rules(): array
    {
        return [
            'nomor_surat' => 'sometimes|required|string|max:150|exists:core_arsip_nomor_surat,nomor_surat',
            'nomor_surat_arsip_id' => 'sometimes|required|integer|exists:core_arsip_nomor_surat,id',
            'judul' => 'sometimes|required|string|max:255',
            'kategori_kebijakan_id' => 'sometimes|required|integer|exists:simpeg_master_kategori_kebijakan,id',
            'tanggal_penetapan' => 'sometimes|required|date',
            'tanggal_berlaku' => 'nullable|date',
            'pejabat_penetap' => 'nullable|string|max:150',
            'file_dokumen' => 'nullable|file|mimes:pdf|max:10240',
            'deskripsi' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nomor_surat.required' => 'Nomor surat resmi wajib diisi.',
            'nomor_surat.exists' => 'Nomor surat tidak terdaftar pada modul Arsip.',
            'nomor_surat_arsip_id.required' => 'Referensi ID nomor surat arsip wajib disertakan.',
            'nomor_surat_arsip_id.exists' => 'Data nomor surat arsip tidak valid.',
            'judul.required' => 'Judul kebijakan / pedoman wajib diisi.',
            'kategori_kebijakan_id.required' => 'Kategori kebijakan wajib dipilih.',
            'kategori_kebijakan_id.exists' => 'Kategori kebijakan yang dipilih tidak valid.',
            'tanggal_penetapan.required' => 'Tanggal penetapan kebijakan wajib diisi.',
            'file_dokumen.mimes' => 'Berkas dokumen harus berformat PDF.',
            'file_dokumen.max' => 'Ukuran berkas dokumen maksimal 10MB.',
        ];
    }
}
