<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi permintaan tarik riwayat presensi dari PresensiV2 ke LMS.
 */
class TarikPresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'integer', 'exists:siakad_kelas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'kelas_id.required' => 'Kelas wajib dipilih.',
            'kelas_id.exists'   => 'Kelas yang dipilih tidak ditemukan.',
            'kelas_id.integer'  => 'Kelas yang dipilih tidak valid.',
        ];
    }

    /**
     * Id kelas yang dipilih.
     */
    public function kelasId(): int
    {
        return (int) $this->validated('kelas_id');
    }
}
