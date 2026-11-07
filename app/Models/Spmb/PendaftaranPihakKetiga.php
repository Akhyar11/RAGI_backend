<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Model;

class PendaftaranPihakKetiga extends Model
{
    protected $table = 'spmb_pendaftaran_pihak_ketiga';

    protected $fillable = ['pendaftaran_id', 'user_id', 'nama', 'urutan'];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
