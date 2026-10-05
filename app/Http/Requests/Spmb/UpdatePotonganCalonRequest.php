<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePotonganCalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.potongan.update');
    }

    public function rules(): array
    {
        $potonganId = $this->route('id');
        $pendaftaranId = \App\Models\Spmb\PotonganCalon::whereKey($potonganId)->value('pendaftaran_id');

        return [
            'komponen_biaya_id' => [
                'sometimes',
                'required',
                'exists:spmb_master_komponen_biaya,id',
                Rule::unique('spmb_potongan_calon', 'komponen_biaya_id')
                    ->ignore($potonganId)
                    ->where(fn ($q) => $q->where('pendaftaran_id', $pendaftaranId)->whereNull('deleted_at')),
            ],
            'nama_potongan' => 'sometimes|required|string|max:150',
            'tipe_potongan' => 'sometimes|required|in:nominal,persen',
            'nilai_potongan' => 'sometimes|required|numeric|min:0',
            'tahap' => 'sometimes|required|in:pendaftaran,daftar_ulang,keduanya',
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
