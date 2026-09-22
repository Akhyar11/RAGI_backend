<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StorePengajuanOperasionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul_pengajuan' => 'required|string|max:255',
            'deskripsi' => 'required|string|min:10',
            'kategori_pengajuan' => 'required|in:pengadaan_barang,non_barang',
            'fakultas_id' => 'required|exists:siakad_fakultas,id',
            'ruangan_id' => 'nullable|exists:sinapra_ruangan,id',
            'unit_kas_id' => 'required|exists:sikeu_unit_kas,id',
            'jenis_pengajuan' => 'nullable|in:operasional,kegiatan,reimbursement,lainnya',
            'nominal_diajukan' => 'required_if:kategori_pengajuan,non_barang|nullable|numeric|min:1000',
            'items' => 'required_if:kategori_pengajuan,pengadaan_barang|nullable|array|min:1',
            'items.*.nama_barang' => 'required_with:items|string|max:255',
            'items.*.qty' => 'required_with:items|numeric|min:0.01',
            'items.*.satuan' => 'nullable|string|max:50',
            'items.*.harga_satuan' => 'required_with:items|numeric|min:1',
            'items.*.keterangan' => 'nullable|string',
            'file_lampiran' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'deskripsi.min' => 'Alasan/alokasi pengajuan minimal 10 karakter.',
            'fakultas_id.exists' => 'Fakultas tidak valid (harus dari master).',
            'ruangan_id.exists' => 'Ruangan tidak valid (harus dari master).',
            'items.required_if' => 'Pengadaan barang wajib memiliki minimal 1 item barang.',
            'nominal_diajukan.required_if' => 'Nominal pengajuan wajib diisi untuk non-barang.',
        ];
    }
}
