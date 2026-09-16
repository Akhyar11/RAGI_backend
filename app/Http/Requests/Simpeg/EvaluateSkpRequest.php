<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateSkpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nilai_skp' => 'required|numeric|min:0|max:100',
            'nilai_bkd' => 'nullable|numeric|min:0',
            'predikat' => 'required|in:sangat_baik,baik,cukup,kurang,sangat_kurang',
            'catatan_evaluator' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|exists:simpeg_skp_item,id',
            'items.*.nilai_capaian' => 'required_with:items|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'nilai_skp.required' => 'Nilai SKP akhir wajib diisi.',
            'nilai_skp.numeric' => 'Nilai SKP harus berupa angka 0 - 100.',
            'predikat.required' => 'Predikat kinerja wajib dipilih.',
            'predikat.in' => 'Predikat kinerja harus salah satu dari: Sangat Baik, Baik, Cukup, Kurang, Sangat Kurang.',
        ];
    }
}
