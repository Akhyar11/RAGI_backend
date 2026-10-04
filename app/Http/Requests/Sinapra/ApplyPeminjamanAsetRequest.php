<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class ApplyPeminjamanAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aset_id' => 'nullable|exists:sinapra_aset,id',
            'aset_ids' => 'nullable|array|min:1',
            'aset_ids.*' => 'required|exists:sinapra_aset,id',
            'keperluan' => 'required|string|max:500',
            'tanggal_pinjam' => 'required|date|after_or_equal:today',
            'tanggal_kembali_rencana' => 'required|date|after_or_equal:tanggal_pinjam',
            'nomor_identitas' => 'nullable|string|max:50',
            'kontak_peminjam' => 'nullable|string|max:50',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasSingle = $this->filled('aset_id');
            $hasMultiple = $this->has('aset_ids') && is_array($this->aset_ids) && count($this->aset_ids) > 0;
            if (!$hasSingle && !$hasMultiple) {
                $validator->errors()->add('aset_id', 'Minimal satu barang aset wajib dipilih untuk dipinjam.');
            }
        });
    }
}
