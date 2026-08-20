<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class DosenPengampu extends Model
{
    protected $table = 'siakad_dosen_pengampu';

    protected $fillable = [
        'kelas_id',
        'dosen_id',
        'peran',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }
}
