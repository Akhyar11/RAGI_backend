<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanPencairanKas extends Model
{
    use HasFactory;

    protected $table = 'sikeu_pengajuan_pencairan_kas';

    protected $fillable = [
        'nomor_pengajuan',
        'unit_kerja_id',
        'fakultas_id',
        'ruangan_id',
        'unit_kas_id',
        'pemohon_id',
        'judul_pengajuan',
        'deskripsi',
        'nominal_diajukan',
        'nominal_disetujui',
        'nama_bank_penerima',
        'nomor_rekening_penerima',
        'nama_rekening_penerima',
        'total_realisasi',
        'sisa_nominal',
        'jenis_pengajuan',
        'kategori_pengajuan',
        'file_lampiran',
        'status',
        'approved_sarpras_by',
        'approved_sarpras_at',
        'approved_pimpinan_by',
        'approved_pimpinan_at',
        'approved_keuangan_by',
        'approved_keuangan_at',
        'approved_direktur_by',
        'approved_direktur_at',
        'tanggal_pencairan',
        'bukti_pencairan_path',
        'kanal',
        'referensi_eksternal',
        'catatan_penolakan',
    ];

    protected $casts = [
        'nominal_diajukan' => 'decimal:2',
        'nominal_disetujui' => 'decimal:2',
        'total_realisasi' => 'decimal:2',
        'sisa_nominal' => 'decimal:2',
        'approved_sarpras_at' => 'datetime',
        'approved_pimpinan_at' => 'datetime',
        'approved_keuangan_at' => 'datetime',
        'approved_direktur_at' => 'datetime',
        'tanggal_pencairan' => 'date',
    ];

    protected $appends = [
        'bukti_pencairan_url',
        'file_lampiran_url',
    ];

    public function getBuktiPencairanUrlAttribute(): ?string
    {
        if (empty($this->bukti_pencairan_path)) {
            return null;
        }

        if (str_starts_with($this->bukti_pencairan_path, 'http://') || str_starts_with($this->bukti_pencairan_path, 'https://')) {
            return $this->bukti_pencairan_path;
        }

        $files = app(\App\Services\Storage\FileStorageService::class);
        return $files->signedUrl($this->bukti_pencairan_path)
            ?? $files->temporaryUrl($this->bukti_pencairan_path, now()->addMinutes(60))
            ?? $files->url($this->bukti_pencairan_path, private: true);
    }

    public function getFileLampiranUrlAttribute(): ?string
    {
        if (empty($this->file_lampiran)) {
            return null;
        }

        if (str_starts_with($this->file_lampiran, 'http://') || str_starts_with($this->file_lampiran, 'https://')) {
            return $this->file_lampiran;
        }

        $files = app(\App\Services\Storage\FileStorageService::class);
        return $files->signedUrl($this->file_lampiran)
            ?? $files->temporaryUrl($this->file_lampiran, now()->addMinutes(60))
            ?? $files->url($this->file_lampiran, private: true);
    }

    public function unitKas()
    {
        return $this->belongsTo(UnitKas::class, 'unit_kas_id');
    }

    public function fakultas()
    {
        return $this->belongsTo(\App\Models\Siakad\Fakultas::class, 'fakultas_id');
    }

    public function ruangan()
    {
        return $this->belongsTo(\App\Models\Ruangan::class, 'ruangan_id');
    }

    public function items()
    {
        return $this->hasMany(PengajuanItem::class, 'pengajuan_id');
    }

    public function lpj()
    {
        return $this->hasMany(LaporanBuktiPelaksanaan::class, 'pengajuan_id');
    }

    public function historyApproval()
    {
        return $this->hasMany(ApprovalHistoryPencairan::class, 'pengajuan_id');
    }

    public function pemohon()
    {
        return $this->belongsTo(\App\Models\User::class, 'pemohon_id');
    }

    public function suratTugas()
    {
        return $this->hasOne(\App\Models\Simpeg\SuratTugas::class, 'sikeu_pencairan_id');
    }
}
