<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'tipe_diskon' => ['required', Rule::in(['persen', 'nominal'])],
            'nilai' => ['required', 'numeric', 'gt:0'],
            'maksimum_potongan' => ['nullable', 'numeric', 'gt:0'],
            'batas_penggunaan' => ['nullable', 'integer', 'gt:0'],
            'berlaku_mulai' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_mulai'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('tipe_diskon') === 'persen' && (float) $this->input('nilai', 0) > 100) {
                $validator->errors()->add('nilai', 'Diskon persentase tidak boleh melebihi 100%.');
            }
        });
    }
}
