<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKonversiTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kampus_asal' => 'required|string|max:255',
            'prodi_asal' => 'required|string|max:255',
            'nomor_sk' => 'nullable|string|max:150',
            'catatan' => 'nullable|string',
            'status' => 'nullable|string|in:draft,diajukan,disetujui,ditolak',
            'details' => 'required|array|min:1',
            'details.*.mata_kuliah_diakui_id' => 'required|exists:siakad_mata_kuliah,id',
            'details.*.kode_mk_asal' => 'required|string|max:50',
            'details.*.nama_mk_asal' => 'required|string|max:255',
            'details.*.sks_asal' => 'required|integer|min:1',
            'details.*.nilai_huruf_asal' => 'required|string|max:5',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $nomorSk = trim((string) $this->input('nomor_sk'));
            if ($nomorSk !== '') {
                $surat = \App\Models\Arsip\NomorSurat::where('nomor_surat', $nomorSk)->first();
                if (!$surat) {
                    $validator->errors()->add('nomor_sk', "Nomor SK '{$nomorSk}' tidak terdaftar di modul Arsip.");
                } elseif ($surat->status === 'dibatalkan') {
                    $validator->errors()->add('nomor_sk', "Nomor SK '{$nomorSk}' berstatus DIBATALKAN.");
                } elseif (!$surat->has_dokumen) {
                    $validator->errors()->add('nomor_sk', "Nomor SK '{$nomorSk}' terdaftar di Arsip, tetapi dokumen fisik SK belum diunggah/kosong.");
                }
            }
        });
    }
}
