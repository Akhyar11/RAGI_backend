<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualInitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_kas_id' => 'required|integer|exists:sikeu_unit_kas,id',
            'items' => 'required|array|min:1|max:50',
            'items.*.tagihan_id' => 'required|integer|exists:sikeu_tagihan_mahasiswa,id',
            'items.*.jumlah_bayar' => 'nullable|numeric|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal 1 tagihan untuk transfer manual.',
            'unit_kas_id.required' => 'Pilih rekening tujuan (BNI / BSN).',
        ];
    }
}
