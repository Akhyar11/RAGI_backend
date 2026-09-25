<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKelasLmsSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'total_pertemuan'         => 'required|integer|min:1|max:32',
            'metode_absensi'          => 'required|string|max:50',
            'batas_min_hadir_persen'  => 'required|integer|min:0|max:100',
            'can_submit_late'         => 'nullable|boolean',
            'show_nilai_to_mahasiswa' => 'nullable|boolean',
            'storage_disk'            => 'nullable|string|max:50',
        ];
    }
}
