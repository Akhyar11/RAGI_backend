<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class BulkAbsensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'absensi'                => 'required|array|min:1',
            'absensi.*.mahasiswa_id' => 'required|integer|exists:siakad_mahasiswa,id',
            'absensi.*.status_id'    => 'required|integer|exists:spmb_master_referensi,id',
            'absensi.*.catatan'      => 'nullable|string|max:255',
        ];
    }
}
