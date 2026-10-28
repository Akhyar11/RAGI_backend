<?php

namespace App\Http\Requests\Spmb;

use App\Models\Arsip\RequestNomorSurat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        $template = $this->route('templateSurat');
        $id = is_object($template) ? $template->id : $template;
        $activeTemplate = filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN);
        $issuanceRequirement = $activeTemplate ? 'required' : 'nullable';

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('spmb_template_surat', 'kode')->ignore($id),
            ],
            'nama' => 'required|string|max:150',
            'jenis_surat' => 'required|string|max:50',
            'hasil' => 'nullable|string|in:diterima,ditolak',
            'label_keputusan' => 'nullable|string|max:150',
            'teks_pernyataan' => 'nullable|string',
            'teks_prodi' => 'nullable|string|max:255',
            'teks_penutup' => 'nullable|string',
            'judul_petunjuk' => 'nullable|string|max:150',
            'module_id' => [
                $issuanceRequirement,
                'integer',
                Rule::exists('core_modules', 'id')->where('code', RequestNomorSurat::MODULE_ORIGIN_SPMB),
            ],
            'klasifikasi_surat_id' => [
                $issuanceRequirement,
                'integer',
                Rule::exists('core_arsip_klasifikasi', 'id')->where('kategori', 'klasifikasi'),
            ],
            'unit_surat_id' => [
                $issuanceRequirement,
                'integer',
                Rule::exists('core_arsip_klasifikasi', 'id')->where('kategori', 'unit')->where('is_active', true),
            ],
            'jalur_masuk_id' => 'nullable|integer|exists:spmb_jalur_masuk,id',
            'gelombang_id' => 'nullable|integer|exists:spmb_gelombang_penerimaan,id',
            'is_active' => 'nullable|boolean',
            'kop_nama_institusi' => 'nullable|string|max:255',
            'kop_nama_sub' => 'nullable|string|max:255',
            'kop_alamat_kontak' => 'nullable|string',
            'format_nomor_surat' => 'nullable|string|max:150',
            'judul_surat' => 'nullable|string|max:255',
            'teks_pembuka' => 'nullable|string',
            'teks_keputusan' => 'nullable|string',
            'petunjuk_daftar_ulang' => 'nullable|string',
            'kota_penetapan' => 'nullable|string|max:100',
            'nama_penandatangan' => 'nullable|string|max:255',
            'jabatan_penandatangan' => 'nullable|string|max:255',
            'nip_penandatangan' => 'nullable|string|max:100',
            'catatan_kaki' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'module_id.required' => 'Pilih modul pemohon untuk template yang aktif.',
            'module_id.exists' => 'Asal permohonan template SK harus menggunakan modul SPMB.',
            'klasifikasi_surat_id.required' => 'Pilih klasifikasi surat dari master Arsip.',
            'klasifikasi_surat_id.exists' => 'Klasifikasi surat harus berasal dari master klasifikasi Arsip.',
            'unit_surat_id.required' => 'Pilih unit penerbit dari master Arsip.',
            'unit_surat_id.exists' => 'Unit penerbit harus berasal dari master unit Arsip yang aktif.',
        ];
    }
}
