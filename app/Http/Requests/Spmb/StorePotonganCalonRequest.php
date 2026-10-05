<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePotonganCalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.potongan.create');
    }

    public function rules(): array
    {
        $pendaftaranId = $this->route('id');

        return [
            'komponen_biaya_id' => [
                'required',
                'exists:spmb_master_komponen_biaya,id',
                Rule::unique('spmb_potongan_calon', 'komponen_biaya_id')
                    ->where(fn ($q) => $q->where('pendaftaran_id', $pendaftaranId)->whereNull('deleted_at')),
            ],
            'nama_potongan' => 'required|string|max:150',
            'tipe_potongan' => 'required|in:nominal,persen',
            'nilai_potongan' => 'required|numeric|min:0',
            'tahap' => 'required|in:pendaftaran,daftar_ulang,keduanya',
            'nomor_sk' => 'nullable|string|max:100',
            'keterangan' => 'nullable|string',
            'berlaku_mulai' => 'nullable|date',
            'berlaku_sampai' => 'nullable|date|after_or_equal:berlaku_mulai',
            'status' => 'nullable|in:draft,aktif,dibatalkan',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('tipe_potongan') === 'persen' && (float) $this->input('nilai_potongan') > 100) {
                $v->errors()->add('nilai_potongan', 'Potongan persen maksimal 100.');
            }
        });
    }
}
