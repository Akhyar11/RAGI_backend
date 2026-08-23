<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pegawai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_pegawai';

    protected $fillable = [
        'user_id',
        'unit_kerja_id',
        'nip',
        'nik',
        'nama_lengkap',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'jenis_pegawai',
        'status_kepegawaian',
        'tanggal_masuk',
        'tanggal_keluar',
        'status',
        'alamat',
        'telepon',
        'sinta_id',
        'scopus_id',
        'google_scholar_id',
        'orcid_id',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date:Y-m-d',
        'tanggal_masuk' => 'date:Y-m-d',
        'tanggal_keluar' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function riwayatJabatan()
    {
        return $this->hasMany(RiwayatJabatan::class, 'pegawai_id');
    }

    public function riwayatPendidikan()
    {
        return $this->hasMany(RiwayatPendidikanPegawai::class, 'pegawai_id');
    }
}
