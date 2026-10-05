<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Siakad\Kelas;

class KelasLmsSetting extends Model
{
    use HasFactory;

    protected $table = 'lms_kelas_setting';

    protected $fillable = [
        'kelas_id',
        'total_pertemuan',
        'metode_absensi',
        'batas_min_hadir_persen',
        'can_submit_late',
        'show_nilai_to_mahasiswa',
        'storage_disk',
    ];

    /**
     * Metode absensi yang sah.
     *
     * Closed-set dari domain yang bersumber dari CHECK constraint kolom
     * `lms_kelas_setting.metode_absensi` di database dan TIDAK punya tabel
     * master, sehingga dideklarasikan sebagai konstanta terpusat di sini
     * (bukan string literal yang diulang di validasi maupun UI).
     *
     * @see database/migrations/2026_09_30_000001_create_lms_tables.php
     */
    public const METODE_MANUAL_DOSEN = 'manual_dosen';
    public const METODE_TOKEN_MAHASISWA = 'token_mahasiswa';
    public const METODE_KEDUANYA = 'keduanya';

    public const METODE_ABSENSI = [
        self::METODE_MANUAL_DOSEN,
        self::METODE_TOKEN_MAHASISWA,
        self::METODE_KEDUANYA,
    ];

    /**
     * Label Bahasa Indonesia untuk setiap metode absensi.
     *
     * @return array<string, string>
     */
    public static function metodeAbsensiLabels(): array
    {
        return [
            self::METODE_MANUAL_DOSEN => 'Absensi Manual oleh Dosen',
            self::METODE_TOKEN_MAHASISWA => 'Token Absensi Mahasiswa',
            self::METODE_KEDUANYA => 'Keduanya (Manual & Token)',
        ];
    }

    /**
     * Nilai default saat kelas belum pernah dikonfigurasi.
     *
     * Diselaraskan dengan default kolom di database.
     *
     * @return array<string, mixed>
     */
    public static function defaultSetting(int $kelasId): array
    {
        return [
            'kelas_id'             => $kelasId,
            'total_pertemuan'     => 16,
            'metode_absensi'       => self::METODE_KEDUANYA,
            'batas_min_hadir_persen' => 75,
            'can_submit_late'      => true,
            'show_nilai_to_mahasiswa' => false,
            'storage_disk'         => null,
        ];
    }

    protected $casts = [
        'total_pertemuan' => 'integer',
        'batas_min_hadir_persen' => 'integer',
        'can_submit_late' => 'boolean',
        'show_nilai_to_mahasiswa' => 'boolean',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}
