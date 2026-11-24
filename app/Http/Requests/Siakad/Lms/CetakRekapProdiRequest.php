<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi parameter cetak PDF rekap kehadiran per program studi.
 *
 * Prodi & tahun akademik adalah entitas master → wajib referensi ID.
 * `program_studi_id` opsional: bila kosong, laporan dikelompokkan per prodi
 * untuk seluruh prodi yang dapat diakses user.
 */
class CetakRekapProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'program_studi_id'  => ['nullable', 'integer', 'exists:siakad_program_studi,id'],
            'tahun_akademik_id' => ['required', 'integer', 'exists:siakad_tahun_akademik,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'program_studi_id.integer'    => 'Program studi harus berupa ID numerik.',
            'program_studi_id.exists'     => 'Program studi tidak ditemukan.',
            'tahun_akademik_id.required'  => 'Tahun akademik wajib diisi.',
            'tahun_akademik_id.integer'   => 'Tahun akademik harus berupa ID numerik.',
            'tahun_akademik_id.exists'    => 'Tahun akademik tidak ditemukan.',
        ];
    }

    public function programStudiId(): ?int
    {
        $value = $this->validated('program_studi_id');

        return $value === null ? null : (int) $value;
    }

    public function tahunAkademikId(): int
    {
        return (int) $this->validated('tahun_akademik_id');
    }
}
