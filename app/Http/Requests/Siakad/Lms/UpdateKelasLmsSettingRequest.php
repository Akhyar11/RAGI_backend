<?php

namespace App\Http\Requests\Siakad\Lms;

use App\Models\Lms\KelasLmsSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Daftar metode diambil dari konstanta model, bukan string literal,
            // agar selalu selaras dengan CHECK constraint kolom di database.
            'metode_absensi'          => ['required', 'string', Rule::in(KelasLmsSetting::METODE_ABSENSI)],
            'batas_min_hadir_persen'  => 'required|integer|min:0|max:100',
            'can_submit_late'         => 'nullable|boolean',
            'show_nilai_to_mahasiswa' => 'nullable|boolean',
            'storage_disk'            => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        $labels = KelasLmsSetting::metodeAbsensiLabels();

        return [
            'total_pertemuan.required' => 'Jumlah pertemuan wajib diisi.',
            'total_pertemuan.min'      => 'Jumlah pertemuan minimal 1.',
            'total_pertemuan.max'      => 'Jumlah pertemuan maksimal 32.',
            'metode_absensi.required'   => 'Metode absensi wajib dipilih.',
            'metode_absensi.in'         => 'Metode absensi tidak valid. Pilihan: '
                . implode(', ', array_map(
                    fn (string $value): string => $labels[$value] . ' (' . $value . ')',
                    KelasLmsSetting::METODE_ABSENSI
                )) . '.',
            'batas_min_hadir_persen.required' => 'Batas minimum hadir wajib diisi.',
            'batas_min_hadir_persen.min'      => 'Batas minimum hadir tidak boleh negatif.',
            'batas_min_hadir_persen.max'      => 'Batas minimum hadir maksimal 100 persen.',
            'storage_disk.max'         => 'Nama storage disk maksimal 50 karakter.',
        ];
    }
}
