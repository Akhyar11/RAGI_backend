<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiwayatPelatihan extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_riwayat_pelatihan';

    protected $fillable = [
        'pegawai_id',
        'nama_kegiatan',
        'jenis_pelatihan_id',
        'peran_id',
        'tingkat_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'jumlah_jam',
        'penyelenggara',
        'tempat',
        'nomor_sertifikat',
        'file_path',
        'tautan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'jumlah_jam' => 'integer',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jenisPelatihan()
    {
        return $this->belongsTo(MasterJenisPelatihan::class, 'jenis_pelatihan_id');
    }

    public function peran()
    {
        return $this->belongsTo(MasterPeranPelatihan::class, 'peran_id');
    }

    public function tingkat()
    {
        return $this->belongsTo(MasterTingkatKegiatan::class, 'tingkat_id');
    }
}
