<?php

namespace App\Http\Requests\Siakad\Obe;

use Illuminate\Foundation\Http\FormRequest;

class BulkMoveSoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.nilai.manage');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.BulkSoalRequest::MAX_IDS],
            'ids.*' => ['required', 'integer', 'exists:siakad_bank_soal,id'],
            'kategori_id' => 'nullable|exists:siakad_bank_soal_kategori,id',
            'rps_mingguan_id' => 'nullable|exists:siakad_rps_mingguan,id',
            'sub_cpmk_id' => 'nullable|exists:siakad_sub_cpmk,id',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu soal untuk dipindahkan.',
        ];
    }

    /**
     * Minimal satu tujuan perpindahan wajib diisi.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasTarget = $this->filled('kategori_id')
                || $this->filled('rps_mingguan_id')
                || $this->filled('sub_cpmk_id');

            if (! $hasTarget) {
                $validator->errors()->add('ids', 'Pilih minimal satu tujuan: kategori, RPS mingguan, atau Sub-CPMK.');
            }
        });
    }
}
