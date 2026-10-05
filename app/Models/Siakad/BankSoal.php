<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class BankSoal extends Model
{
    protected $table = 'siakad_bank_soal';

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
