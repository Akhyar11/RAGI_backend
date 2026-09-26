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
        'nama_bank',
        'nomor_rekening',
        'nama_rekening',
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
        'nominal_disetujui',
        'sikeu_pencairan_id',
        'status_pencairan',
    ];

    protected $casts = [
        'tanggal_berangkat' => 'date',
        'tanggal_kembali' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'estimasi_biaya' => 'decimal:2',
        'nominal_disetujui' => 'decimal:2',
        'biaya_realisasi' => 'decimal:2',
        'tanggal_upload_lpj' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'sisa_nominal',
        'file_surat_tugas_url',
        'file_lpj_url',
    ];

    public function getSisaNominalAttribute(): float
    {
        $disetujui = (float) ($this->nominal_disetujui ?? 0);
        $realisasi = (float) ($this->biaya_realisasi ?? 0);
        return $disetujui - $realisasi;
    }

    public function getFileSuratTugasUrlAttribute(): ?string
    {
        if (empty($this->file_surat_tugas)) {
            return null;
        }

        if (str_starts_with($this->file_surat_tugas, 'http://') || str_starts_with($this->file_surat_tugas, 'https://')) {
            return $this->file_surat_tugas;
        }

        $files = app(\App\Services\Storage\FileStorageService::class);
        return $files->signedUrl($this->file_surat_tugas)
            ?? $files->temporaryUrl($this->file_surat_tugas, now()->addMinutes(60))
            ?? $files->url($this->file_surat_tugas, private: true);
    }

    public function getFileLpjUrlAttribute(): ?string
    {
        if (empty($this->file_lpj)) {
            return null;
        }

        if (str_starts_with($this->file_lpj, 'http://') || str_starts_with($this->file_lpj, 'https://')) {
            return $this->file_lpj;
        }

        $files = app(\App\Services\Storage\FileStorageService::class);
        return $files->signedUrl($this->file_lpj)
            ?? $files->temporaryUrl($this->file_lpj, now()->addMinutes(60))
            ?? $files->url($this->file_lpj, private: true);
    }

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

    public function pencairanKas()
    {
        return $this->belongsTo(\App\Models\Sikeu\PengajuanPencairanKas::class, 'sikeu_pencairan_id');
    }
}
