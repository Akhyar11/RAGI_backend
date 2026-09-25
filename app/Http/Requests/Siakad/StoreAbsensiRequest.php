<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreAbsensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'absensi' => 'required|array',
            'absensi.*.mahasiswa_id' => 'required|exists:siakad_mahasiswa,id',
            'absensi.*.status' => ['required', 'string', new MasterReferensiExists('status_absensi')],
            'absensi.*.catatan' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'absensi.*.status.required' => 'Status kehadiran wajib diisi.',
        ];
    }
}
