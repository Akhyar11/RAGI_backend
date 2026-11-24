<?php

namespace App\Http\Requests\Siakad\Lms;

use App\Models\Siakad\Pertemuan;
use Illuminate\Validation\Rule;

/**
 * Validasi payload jurnal presensi yang ditulis dosen pengampu.
 *
 * Field inti pertemuan tetap memakai aturan UpdatePertemuanRequest agar satu
 * form "Isian" tidak punya dua sumber kebenaran. Field jurnal ditambahkan di
 * sini: jenis kegiatan (closed-set domain, bukan entitas master), ruangan
 * (ID master sinapra_ruangan) dan bukti pembelajaran privat.
 */
class UpdateJurnalPertemuanRequest extends UpdatePertemuanRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'jenis_kegiatan' => ['nullable', 'string', 'max:100', Rule::in(Pertemuan::JENIS_KEGIATAN)],
            // Ruangan adalah entitas master → wajib referensi ID, bukan label.
            'ruangan_id' => ['nullable', 'integer', 'exists:sinapra_ruangan,id'],
            'bukti_pembelajaran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
    }

    public function messages(): array
    {
        $labels = Pertemuan::jenisKegiatanOptions();

        return array_merge(parent::messages(), [
            'jenis_kegiatan.in' => 'Jenis kegiatan tidak valid. Pilihan: '
                . implode(', ', array_map(
                    fn (string $value): string => $labels[$value] . ' (' . $value . ')',
                    Pertemuan::JENIS_KEGIATAN
                )) . '.',
            'ruangan_id.exists' => 'Ruangan yang dipilih tidak ditemukan.',
            'bukti_pembelajaran.file' => 'Bukti pembelajaran harus berupa berkas.',
            'bukti_pembelajaran.mimes' => 'Bukti pembelajaran harus berformat PDF, JPG, atau PNG.',
            'bukti_pembelajaran.max' => 'Ukuran bukti pembelajaran maksimal 5 MB.',
        ]);
    }
}
