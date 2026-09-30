<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKonversiTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kampus_asal' => 'required|string|max:255',
            'prodi_asal' => 'required|string|max:255',
            'catatan' => 'nullable|string',
            'status' => 'nullable|string|in:draft,diajukan,disetujui,ditolak',
            'details' => 'required|array|min:1',
            'details.*.mata_kuliah_diakui_id' => 'required|exists:siakad_mata_kuliah,id',
            'details.*.kode_mk_asal' => 'required|string|max:50',
            'details.*.nama_mk_asal' => 'required|string|max:255',
            'details.*.sks_asal' => 'required|integer|min:1',
            'details.*.nilai_huruf_asal' => 'required|string|max:5',
        ];
    }
}
