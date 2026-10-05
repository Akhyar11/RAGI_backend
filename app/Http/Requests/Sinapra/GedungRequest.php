<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GedungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status') && $this->input('status') === 'nonaktif') {
            $this->merge(['status' => 'tidak_aktif']);
        }
    }

    public function rules(): array
    {
        $gedung = $this->route('gedung');
        $gedungId = $gedung instanceof \App\Models\Gedung ? $gedung->id : $gedung;

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sinapra_gedung', 'kode')->ignore($gedungId)->whereNull('deleted_at'),
            ],
            'nama' => 'required|string|max:150',
            'jumlah_lantai' => 'required|integer|min:1',
            'alamat' => 'nullable|string',
            'tahun_bangun' => 'nullable|integer|min:1900|max:' . date('Y'),
            'luas_m2' => 'nullable|numeric|min:0',
            'status' => 'required|in:aktif,renovasi,nonaktif,tidak_aktif',
        ];
    }
}
