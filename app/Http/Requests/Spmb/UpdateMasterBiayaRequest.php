<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterBiayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        return [
            'master_tipe_jalur_id' => 'sometimes|required|exists:core_master_tipe_jalur,id',
            'program_studi_id' => 'sometimes|required|exists:siakad_program_studi,id',
            'nomor_sk' => 'nullable|string|max:150',
            'is_active' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.komponen_biaya_id' => 'required|exists:spmb_master_komponen_biaya,id',
            'items.*.nominal' => 'required|numeric|min:0',
            'items.*.dibebankan_saat_pendaftaran' => 'nullable|boolean',
            'items.*.berlaku_diskon' => 'nullable|boolean',
            'items.*.keterangan' => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $nomorSk = trim((string) $this->input('nomor_sk'));
            if ($nomorSk !== '') {
                $surat = \App\Models\Arsip\NomorSurat::with('request')
                    ->where('nomor_surat', $nomorSk)
                    ->first();

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
