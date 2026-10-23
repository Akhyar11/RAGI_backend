<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi permintaan sinkronisasi ulang LMS -> PresensiV2.
 *
 * Cakupan sinkronisasi dipilih satu dari: satu pertemuan (`pertemuan_id`)
 * atau seluruh pertemuan sebuah kelas (`kelas_id`).
 */
class SyncPresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pertemuan_id' => ['nullable', 'integer', 'exists:siakad_pertemuan,id'],
            'kelas_id'     => ['nullable', 'integer', 'exists:siakad_kelas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'pertemuan_id.exists' => 'Pertemuan yang dipilih tidak ditemukan.',
            'pertemuan_id.integer' => 'Pertemuan yang dipilih tidak valid.',
            'kelas_id.exists'      => 'Kelas yang dipilih tidak ditemukan.',
            'kelas_id.integer'     => 'Kelas yang dipilih tidak valid.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('pertemuan_id') && ! $this->filled('kelas_id')) {
                $validator->errors()->add(
                    'pertemuan_id',
                    'Isi pertemuan_id atau kelas_id untuk menentukan cakupan sinkronisasi.'
                );
            }
        });
    }

    /**
     * Id pertemuan yang dipilih (null bila sinkronisasi per kelas).
     */
    public function pertemuanId(): ?int
    {
        return $this->filled('pertemuan_id') ? (int) $this->validated('pertemuan_id') : null;
    }

    /**
     * Id kelas yang dipilih (null bila sinkronisasi per pertemuan).
     */
    public function kelasId(): ?int
    {
        return $this->filled('kelas_id') ? (int) $this->validated('kelas_id') : null;
    }
}
