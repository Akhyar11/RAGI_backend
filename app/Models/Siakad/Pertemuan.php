<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class Pertemuan extends Model
{
    protected $table = 'siakad_pertemuan';

    /**
     * Status pertemuan yang sah.
     *
     * Nilai-nilai ini adalah closed-set dari domain (CHECK constraint kolom
     * `status_pertemuan` di DB) dan TIDUK punya tabel master, sehingga
     * dideklarasikan sebagai konstanta terpusat di sini — bukan diulang
     * sebagai string literal pada validasi request maupun service.
     *
     * @see database/migrations/2026_09_30_000002_alter_siakad_pertemuan_add_lms_columns.php
     */
    public const STATUS_BELUM = 'belum';
    public const STATUS_BERLANGSUNG = 'berlangsung';
    public const STATUS_SELESAI = 'selesai';

    public const STATUSES = [
        self::STATUS_BELUM,
        self::STATUS_BERLANGSUNG,
        self::STATUS_SELESAI,
    ];

    protected $fillable = [
        'kelas_id',
        'pertemuan_ke',
        'tanggal',
        'materi',
        'catatan_pertemuan',
        'status_pertemuan',
        'token_absensi',
        'token_expired_at',
        'window_menit',
        'token_rotated_at',
        'presensi_closed_at',
        'jam_mulai',
        'jam_selesai',
    ];

    protected $casts = [
        'token_expired_at' => 'datetime',
        'token_rotated_at' => 'datetime',
        'presensi_closed_at' => 'datetime',
    ];

    /**
     * Label Bahasa Indonesia untuk setiap status (untuk pesan validasi/dokumentasi).
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_BELUM => 'Belum Berlangsung',
            self::STATUS_BERLANGSUNG => 'Berlangsung',
            self::STATUS_SELESAI => 'Selesai',
        ];
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function absensi()
    {
        return $this->hasMany(AbsensiMahasiswa::class, 'pertemuan_id');
    }

    public function materiList()
    {
        return $this->hasMany(\App\Models\Lms\MateriPertemuan::class, 'pertemuan_id')->orderBy('urutan');
    }

    public function tugasList()
    {
        return $this->hasMany(\App\Models\Lms\Tugas::class, 'pertemuan_id');
    }

    public function quizList()
    {
        return $this->hasMany(\App\Models\Lms\Quiz::class, 'pertemuan_id')->orderBy('id');
    }

    public function izinAbsensiList()
    {
        return $this->hasMany(\App\Models\Lms\IzinAbsensi::class, 'pertemuan_id');
    }
}
