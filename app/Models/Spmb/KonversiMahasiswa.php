<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Siakad\Mahasiswa;
use App\Models\User;

class KonversiMahasiswa extends Model
{
    use HasFactory;

    protected $table = 'konversi_mahasiswa';
    
    const UPDATED_AT = null;

    protected $fillable = [
        'pendaftaran_id',
        'mahasiswa_id',
        'nim_diterbitkan',
        'diproses_oleh',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function pemroses()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
