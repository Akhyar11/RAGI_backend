<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class KonversiTransferDetail extends Model
{
    protected $table = 'siakad_konversi_transfer_detail';

    protected $fillable = [
        'konversi_id',
        'mata_kuliah_diakui_id',
        'kode_mk_asal',
        'nama_mk_asal',
        'sks_asal',
        'nilai_huruf_asal',
    ];

    protected $casts = [
        'sks_asal' => 'integer',
    ];

    public function konversi()
    {
        return $this->belongsTo(KonversiTransfer::class, 'konversi_id');
    }

    public function mataKuliahDiakui()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_diakui_id');
    }
}
