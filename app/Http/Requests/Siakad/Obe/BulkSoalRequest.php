<?php

namespace App\Http\Requests\Siakad\Obe;

use Illuminate\Foundation\Http\FormRequest;

class BulkSoalRequest extends FormRequest
{
    /** Batas jumlah soal yang boleh diproses sekaligus. */
    public const MAX_IDS = 500;

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.nilai.manage');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['required', 'integer', 'exists:siakad_bank_soal,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu soal untuk diproses.',
            'ids.max' => 'Maksimal '.self::MAX_IDS.' soal diproses sekaligus.',
        ];
    }
}
