<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Spmb\SpmbKodeDiskon;

class StoreSpmbKodeDiskonRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('kode')) {
            $this->merge(['kode' => Str::upper(trim((string) $this->input('kode')))]);
        }
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('spmb_kode_diskon', 'kode')],
            'nama' => ['required', 'string', 'max:150'],
            'tipe_diskon' => ['required', Rule::in(SpmbKodeDiskon::TIPE_DISKON)],
            'nilai' => ['required', 'numeric', 'gt:0'],
            'maksimum_potongan' => ['nullable', 'numeric', 'gt:0'],
            'jalur_masuk_ids' => ['sometimes', 'array'],
            'jalur_masuk_ids.*' => ['required', 'integer', 'distinct', 'exists:spmb_jalur_masuk,id'],
            'master_tipe_jalur_ids' => ['prohibited'],
            'batas_penggunaan' => ['nullable', 'integer', 'gt:0'],
            'berlaku_mulai' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_mulai'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('tipe_diskon') === SpmbKodeDiskon::TIPE_PERSEN && (float) $this->input('nilai', 0) > 100) {
                $validator->errors()->add('nilai', 'Diskon persentase tidak boleh melebihi 100%.');
            }
            if ($this->input('tipe_diskon') === SpmbKodeDiskon::TIPE_TOTAL_TETAP && $this->filled('maksimum_potongan')) {
                $validator->errors()->add('maksimum_potongan', 'Maksimum potongan tidak berlaku untuk kode total tetap.');
            }
        });
    }
}
