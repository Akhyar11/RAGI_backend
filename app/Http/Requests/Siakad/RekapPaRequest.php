<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class RekapPaRequest extends FormRequest
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
            'dosen_id' => 'nullable|exists:siakad_dosen,id',
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'dari_tanggal' => 'nullable|date',
            'sampai_tanggal' => 'nullable|date',
        ];
    }
}
