<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'evidence_type' => ['sometimes', 'required', Rule::in(['arsip', 'upload'])],
            'nomor_sk' => 'nullable|prohibited_if:evidence_type,upload|string|max:150',
            'dokumen_pendukung' => 'nullable|prohibited_if:evidence_type,arsip|file|mimes:pdf,jpg,jpeg,png|max:5120',
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

    protected function prepareForValidation(): void
    {
        if ($this->filled('evidence_type')) {
            return;
        }

        if ($this->hasFile('dokumen_pendukung')) {
            $this->merge(['evidence_type' => 'upload']);
        } elseif ($this->has('nomor_sk')) {
            $this->merge(['evidence_type' => 'arsip']);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->has('evidence_type')) {
                return;
            }

            $biaya = \App\Models\Spmb\MasterBiaya::find($this->route('id'));
            $evidenceType = $this->input('evidence_type');
            if ($evidenceType === 'upload') {
                $hasExistingDocument = (bool) $biaya?->dokumen_pendukung_path;
                if (!$this->hasFile('dokumen_pendukung') && !$hasExistingDocument) {
                    $validator->errors()->add('dokumen_pendukung', 'Dokumen pendukung wajib diunggah.');
                }

                return;
            }

            if ($evidenceType !== 'arsip') {
                return;
            }

            $nomorSk = trim((string) $this->input('nomor_sk'));
            if ($nomorSk === '') {
                $validator->errors()->add('nomor_sk', 'Nomor SK wajib diisi untuk basis dokumen Arsip.');
                return;
            }

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
