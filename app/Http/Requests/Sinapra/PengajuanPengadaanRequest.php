<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class PengajuanPengadaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => 'required|exists:unit_kerja,id',
            'judul' => 'required|string|max:150',
            'alasan_kebutuhan' => 'required|string',
            'tanggal_pengajuan' => 'nullable|date',
            'status' => 'nullable|in:draft,diajukan,disetujui,ditolak,proses_pengadaan,selesai',
            'details' => 'required|array|min:1',
            'details.*.kategori_aset_id' => 'required|exists:kategori_aset,id',
            'details.*.nama_barang' => 'required|string|max:150',
            'details.*.spesifikasi' => 'nullable|string',
            'details.*.jumlah' => 'required|integer|min:1',
            'details.*.satuan' => 'required|string|max:50',
            'details.*.harga_satuan_estimasi' => 'required|numeric|min:0',
        ];
    }
}
