<?php

namespace App\Models\Siakad;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pkl extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_pkl';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_SEDANG_BERJALAN = 'sedang_berjalan';
    public const STATUS_LAPORAN_SELESAI = 'laporan_selesai';
    public const STATUS_DINILAI = 'dinilai';
    public const STATUS_DITOLAK = 'ditolak';

    public const JENIS_PKL_REGULER = 'pkl_reguler';
    public const JENIS_MAGANG_INDUSTRI = 'magang_industri';
    public const JENIS_MAGANG_MBKM = 'magang_mbkm';
    public const JENIS_STUDI_INDEPENDEN = 'studi_independen';

    protected $fillable = [
        'mahasiswa_id',
        'tahun_akademik_id',
        'program_studi_id',
        'dosen_pembimbing_id',
        'jenis',
        'judul_laporan',
        'instansi_nama',
        'instansi_alamat',
        'instansi_kota',
        'pembimbing_lapangan_nama',
        'pembimbing_lapangan_kontak',
        'pembimbing_lapangan_jabatan',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'catatan_penolakan',
        'berkas_permohonan',
        'berkas_laporan_akhir',
        'berkas_sertifikat',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function dosenPembimbing(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_pembimbing_id');
    }

    public function bimbingans(): HasMany
    {
        return $this->hasMany(PklBimbingan::class, 'pkl_id');
    }

    public function penilaian(): HasOne
    {
        return $this->hasOne(PklPenilaian::class, 'pkl_id');
    }
}
