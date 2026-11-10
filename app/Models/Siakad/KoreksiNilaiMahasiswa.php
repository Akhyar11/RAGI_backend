<?php

namespace App\Models\Siakad;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class KoreksiNilaiMahasiswa extends Model
{
    public const STATUS_MENUNGGU = 'menunggu';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';

    public const JENIS_KONVENSIONAL = 'konvensional';
    public const JENIS_KOMPONEN_OBE = 'komponen_obe';
    public const JENIS_CPMK_OBE = 'cpmk_obe';

    protected $table = 'siakad_nilai_koreksi';

    protected $fillable = [
        'krs_detail_id',
        'jenis_nilai',
        'nilai_mahasiswa_id',
        'nilai_komponen_mahasiswa_id',
        'ketercapaian_cpmk_mahasiswa_id',
        'nilai_lama',
        'nilai_baru',
        'alasan_koreksi',
        'status',
        'diajukan_oleh',
        'diputuskan_oleh',
        'diputuskan_at',
        'catatan_reviewer',
    ];

    protected $casts = [
        'nilai_lama' => 'array',
        'nilai_baru' => 'array',
        'diputuskan_at' => 'datetime',
    ];

    public function krsDetail()
    {
        return $this->belongsTo(KrsDetail::class, 'krs_detail_id');
    }

    public function nilaiMahasiswa()
    {
        return $this->belongsTo(NilaiMahasiswa::class, 'nilai_mahasiswa_id');
    }

    public function nilaiKomponenMahasiswa()
    {
        return $this->belongsTo(NilaiKomponenMahasiswa::class, 'nilai_komponen_mahasiswa_id');
    }

    public function ketercapaianCpmkMahasiswa()
    {
        return $this->belongsTo(KetercapaianCpmkMahasiswa::class, 'ketercapaian_cpmk_mahasiswa_id');
    }

    public function diajukanOleh()
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function diputuskanOleh()
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }
}