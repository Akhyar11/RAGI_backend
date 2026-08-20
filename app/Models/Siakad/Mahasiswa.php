<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Spmb\MasterProgramStudi;

class Mahasiswa extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_mahasiswa';

    protected $fillable = [
        'user_id',
        'program_studi_id',
        'konversi_id',
        'nim',
        'nama_lengkap',
        'nik',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'telepon',
        'angkatan',
        'tanggal_masuk',
        'status',
        'dosen_wali_id',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_masuk' => 'date',
        'angkatan' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function dosenWali()
    {
        return $this->belongsTo(Dosen::class, 'dosen_wali_id');
    }

    public function konversiTransfer()
    {
        return $this->belongsTo(KonversiTransfer::class, 'konversi_id');
    }

    public function krs()
    {
        return $this->hasMany(Krs::class, 'mahasiswa_id');
    }

    public function khs()
    {
        return $this->hasMany(Khs::class, 'mahasiswa_id');
    }
}
