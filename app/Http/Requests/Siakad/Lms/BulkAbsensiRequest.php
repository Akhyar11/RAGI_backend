<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'absensi.*.status_id'    => [
                'required',
                'integer',
                Rule::exists('spmb_master_referensi', 'id')->where(
                    fn ($query) => $query
                        ->where('tipe', 'status_absensi')
                        ->where('modul', 'siakad')
                        ->where('is_active', true)
                ),
            ],
            'absensi.*.catatan'      => 'nullable|string|max:255',
        ];
    }
}
