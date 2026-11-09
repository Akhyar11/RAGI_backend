<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class KonversiTransfer extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_konversi_transfer';

    protected $fillable = [
        'mahasiswa_id',
        'no_transaksi',
        'kampus_asal',
        'prodi_asal',
        'nomor_sk',
        'diproses_oleh',
        'disetujui_pa_oleh',
        'disetujui_pa_pada',
        'disetujui_kaprodi_oleh',
        'disetujui_kaprodi_pada',
        'status',
        'catatan',
    ];

    protected $casts = [
        'disetujui_pa_pada' => 'datetime',
        'disetujui_kaprodi_pada' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function diprosesOleh()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function details()
    {
        return $this->hasMany(KonversiTransferDetail::class, 'konversi_id');
    }

    public function arsipNomorSurat()
    {
        return $this->belongsTo(\App\Models\Arsip\NomorSurat::class, 'nomor_sk', 'nomor_surat');
    }
}
