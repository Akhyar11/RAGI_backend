<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterBiayaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        return [
            'jalur_masuk_id' => 'required|exists:spmb_jalur_masuk,id',
            'master_tipe_jalur_id' => 'prohibited',
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
            'evidence_type' => ['required', Rule::in(['arsip', 'upload'])],
            'nomor_sk' => 'nullable|required_if:evidence_type,arsip|prohibited_if:evidence_type,upload|string|max:150',
            'dokumen_pendukung' => 'nullable|required_if:evidence_type,upload|prohibited_if:evidence_type,arsip|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'is_active' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
            'items' => 'required|array',
            'items.*.komponen_biaya_id' => 'required|exists:spmb_master_komponen_biaya,id',
            'items.*.nominal' => 'required|numeric|min:0',
            'items.*.dibebankan_saat_pendaftaran' => 'nullable|boolean',
            'items.*.berlaku_diskon' => 'nullable|boolean',
            'items.*.keterangan' => 'nullable|string|max:255',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('evidence_type')) {
            return;
        }

        $this->merge([
            'evidence_type' => $this->hasFile('dokumen_pendukung') ? 'upload' : ($this->filled('nomor_sk') ? 'arsip' : null),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('evidence_type') !== 'arsip') {
                return;
            }

            $nomorSk = trim((string) $this->input('nomor_sk'));
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
        });
    }
}
