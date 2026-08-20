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
        'diproses_oleh',
        'status',
        'catatan',
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
}
