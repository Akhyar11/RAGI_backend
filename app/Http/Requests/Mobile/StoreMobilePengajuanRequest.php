<?php

namespace App\Http\Requests\Mobile;

use App\Models\Sikeu\PengajuanPencairanKas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMobilePengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sikeu.pengajuan.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'judul_pengajuan' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'jenis_pengajuan' => ['required', 'string', Rule::in(PengajuanPencairanKas::ALL_JENIS)],
            'nominal_diajukan' => 'required|numeric|min:1',
            'nama_bank_penerima' => 'nullable|string|max:100',
            'nomor_rekening_penerima' => 'nullable|string|max:50',
            'nama_rekening_penerima' => 'nullable|string|max:150',
            'unit_kas_id' => 'nullable|exists:sikeu_unit_kas,id',
            'file_lampiran' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'judul_pengajuan.required' => 'Judul pengajuan keuangan wajib diisi.',
            'jenis_pengajuan.required' => 'Jenis pengajuan wajib dipilih.',
            'jenis_pengajuan.in' => 'Jenis pengajuan tidak valid.',
            'nominal_diajukan.required' => 'Nominal pengajuan wajib diisi.',
            'nominal_diajukan.numeric' => 'Nominal pengajuan harus berupa angka.',
            'nominal_diajukan.min' => 'Nominal pengajuan minimal Rp 1.',
        ];
    }
}
