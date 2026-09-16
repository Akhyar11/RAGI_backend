<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratTugas extends Model
{
    use SoftDeletes;

    protected $table = 'simpeg_surat_tugas';

    protected $fillable = [
        'nomor_surat',
        'pegawai_id',
        'kategori_kegiatan_id',
        'jenis_transportasi_id',
        'nama_kegiatan',
        'tempat_berangkat',
        'lokasi_tujuan',
        'tanggal_berangkat',
        'tanggal_kembali',
        'tanggal_mulai',
        'tanggal_selesai',
        'maksud_tujuan',
        'beban_anggaran',
        'estimasi_biaya',
        'biaya_realisasi',
        'laporan_kegiatan',
        'kendaraan_dinas',
        'nama_driver',
        'kontak_driver',
        'keterangan',
        'file_surat_tugas',
        'file_lpj',
        'tanggal_upload_lpj',
        'status',
        'catatan_approval',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'tanggal_berangkat' => 'date',
        'tanggal_kembali' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'estimasi_biaya' => 'decimal:2',
        'biaya_realisasi' => 'decimal:2',
        'tanggal_upload_lpj' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function kategoriKegiatan()
    {
        return $this->belongsTo(MasterKategoriKegiatanTugas::class, 'kategori_kegiatan_id');
    }

    public function jenisTransportasi()
    {
        return $this->belongsTo(MasterJenisTransportasi::class, 'jenis_transportasi_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function anggota()
    {
        return $this->hasMany(SuratTugasAnggota::class, 'surat_tugas_id');
    }

    public function anggotaPegawai()
    {
        return $this->belongsToMany(Pegawai::class, 'simpeg_surat_tugas_anggota', 'surat_tugas_id', 'pegawai_id')
            ->withPivot('peran', 'keterangan')
            ->withTimestamps();
    }
}
