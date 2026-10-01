<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        return [
            'kode' => 'required|string|max:50|unique:spmb_template_surat,kode',
            'nama' => 'required|string|max:150',
            'jenis_surat' => 'required|string|max:50',
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
}
