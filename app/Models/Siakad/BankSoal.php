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
        'pertanyaan',
        'bobot',
        'kunci_jawaban',
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
}
