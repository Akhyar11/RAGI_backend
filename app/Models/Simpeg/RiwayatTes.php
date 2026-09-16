<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiwayatTes extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_riwayat_tes';

    protected $fillable = [
        'pegawai_id',
        'jenis_tes_id',
        'nama_tes',
        'penyelenggara',
        'tahun',
        'skor',
        'masa_berlaku',
        'file_path',
        'tautan',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'skor' => 'float',
        'masa_berlaku' => 'date',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jenisTes()
    {
        return $this->belongsTo(MasterJenisTes::class, 'jenis_tes_id');
    }
}
