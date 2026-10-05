<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class TetapkanHasilSeleksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.seleksi.update');
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:lulus,tidak_lulus,cadangan',
            'program_studi_diterima_id' => 'nullable|exists:siakad_program_studi,id',
            'nilai_total' => 'nullable|numeric|min:0',
            'peringkat' => 'nullable|integer|min:1',
            'catatan' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('status') === 'lulus' && ! $this->filled('program_studi_diterima_id')) {
                $v->errors()->add('program_studi_diterima_id', 'Program studi diterima wajib dipilih untuk status lulus.');
            }
        });
    }
}
