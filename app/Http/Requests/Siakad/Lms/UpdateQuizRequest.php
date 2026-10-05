<?php

namespace App\Http\Requests\Siakad\Lms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('siakad.kelas.manage');
    }

    public function rules(): array
    {
        return [
            'komponen_penilaian_id' => 'nullable|exists:siakad_komponen_penilaian,id',
            'judul' => 'sometimes|required|string|max:255',
            'deskripsi' => 'nullable|string',
            'durasi_menit' => 'nullable|integer|min:1|max:1440',
            'max_attempt' => 'sometimes|required|integer|min:1|max:10',
            'acak_soal' => 'nullable|boolean',
            'acak_jawaban' => 'nullable|boolean',
            'batch_size' => 'nullable|integer|min:1|max:200',
            'dibuka_at' => 'nullable|date',
            'ditutup_at' => 'nullable|date|after_or_equal:dibuka_at',
            'is_published' => 'nullable|boolean',
            'kode_akses' => 'nullable|string|max:20',
            'is_archived' => 'nullable|boolean',
        ];
    }
}
