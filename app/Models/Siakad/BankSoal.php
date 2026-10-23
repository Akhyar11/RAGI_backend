<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class BankSoal extends Model
{
    protected $table = 'siakad_bank_soal';

    /**
     * Tipe soal — closed-set domain (sesuai enum kolom `tipe_soal`).
     */
    public const TIPE_PILIHAN_GANDA = 'pilihan_ganda';
    public const TIPE_ISIAN_SINGKAT = 'isian_singkat';
    public const TIPE_URAIAN = 'uraian';

    public const TIPE = [
        self::TIPE_PILIHAN_GANDA,
        self::TIPE_ISIAN_SINGKAT,
        self::TIPE_URAIAN,
    ];

    /**
     * Tingkat kesulitan — closed-set domain (sesuai enum kolom `tingkat_kesulitan`).
     */
    public const TINGKAT_MUDAH = 'mudah';
    public const TINGKAT_SEDANG = 'sedang';
    public const TINGKAT_SUKAR = 'sukar';

    public const TINGKAT = [
        self::TINGKAT_MUDAH,
        self::TINGKAT_SEDANG,
        self::TINGKAT_SUKAR,
    ];

    protected $fillable = [
        'rps_id',
        'rps_mingguan_id',
        'sub_cpmk_id',
        'kategori_id',
        'tipe_soal',
        'tingkat_kesulitan',
        'pertanyaan',
        'gambar_path',
        'bobot',
        'kunci_jawaban',
        'pembahasan',
        'dibuat_oleh',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
    ];

    public function rps()
    {
        return $this->belongsTo(Rps::class, 'rps_id');
    }

    public function mingguan()
    {
        return $this->belongsTo(RpsMingguan::class, 'rps_mingguan_id');
    }

    public function subCpmk()
    {
        return $this->belongsTo(SubCpmk::class, 'sub_cpmk_id');
    }

    public function kategori()
    {
        return $this->belongsTo(BankSoalKategori::class, 'kategori_id');
    }

    public function opsi()
    {
        return $this->hasMany(BankSoalOpsi::class, 'bank_soal_id')->orderBy('urutan');
    }
}
