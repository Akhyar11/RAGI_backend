<?php

namespace App\Http\Requests\Siakad\Lms;

use App\Models\Siakad\Pertemuan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePertemuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'pertemuan_ke'      => 'required|integer|min:1|max:16',
            'tanggal'           => 'required|date',
            'materi'            => 'nullable|string|max:255',
            'catatan_pertemuan' => 'nullable|string',
            'jam_mulai'         => 'nullable|date_format:H:i',
            'jam_selesai'       => 'nullable|date_format:H:i|after:jam_mulai',
            // Daftar status diambil dari konstanta model, bukan string literal,
            // agar selalu selaras dengan CHECK constraint kolom di database.
            'status_pertemuan'  => ['nullable', 'string', Rule::in(Pertemuan::STATUSES)],
        ];
    }

    public function messages(): array
    {
        $labels = Pertemuan::statusLabels();

        return [
            'pertemuan_ke.required'   => 'Nomor pertemuan wajib diisi.',
            'pertemuan_ke.min'        => 'Nomor pertemuan minimal 1.',
            'pertemuan_ke.max'        => 'Nomor pertemuan maksimal 16.',
            'tanggal.required'         => 'Tanggal pertemuan wajib diisi.',
            'materi.max'               => 'Materi maksimal 255 karakter.',
            'jam_mulai.date_format'   => 'Format jam mulai harus HH:MM.',
            'jam_selesai.date_format' => 'Format jam selesai harus HH:MM.',
            'jam_selesai.after'        => 'Jam selesai harus setelah jam mulai.',
            'status_pertemuan.in'     => 'Status pertemuan tidak valid. Pilihan: '
                . implode(', ', array_map(
                    fn (string $value): string => $labels[$value] . ' (' . $value . ')',
                    Pertemuan::STATUSES
                )) . '.',
        ];
    }
}