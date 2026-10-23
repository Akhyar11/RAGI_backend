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
use Illuminate\Database\Eloquent\SoftDeletes;

class TugasAkhir extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_tugas_akhir';

    public const TAHAP_PROPOSAL = 'proposal';
    public const TAHAP_PENELITIAN = 'penelitian';
    public const TAHAP_SEMINAR_HASIL = 'seminar_hasil';
    public const TAHAP_SIDANG_SKRIPSI = 'sidang_skripsi';
    public const TAHAP_REVISI_AKHIR = 'revisi_akhir';
    public const TAHAP_LULUS = 'lulus';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DISETUJUI_PRODI = 'disetujui_prodi';
    public const STATUS_SEDANG_BIMBINGAN = 'sedang_bimbingan';
    public const STATUS_SIAP_SIDANG = 'siap_sidang';
    public const STATUS_REVISI = 'revisi';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'mahasiswa_id',
        'tahun_akademik_id',
        'program_studi_id',
        'judul_utama',
        'judul_inggris',
        'bidang_kajian',
        'abstrak',
        'dosen_pembimbing_1_id',
        'dosen_pembimbing_2_id',
        'tahap',
        'status',
        'nomor_sk_pembimbing',
        'tanggal_sk_pembimbing',
        'berkas_proposal',
        'berkas_laporan_akhir',
        'berkas_persetujuan_sidang',
        'total_sesi_bimbingan',
        'nilai_angka_akhir',
        'nilai_huruf_akhir',
        'bobot_mutu_akhir',
        'catatan_prodi',
    ];

    protected $casts = [
        'tanggal_sk_pembimbing' => 'date',
        'total_sesi_bimbingan' => 'integer',
        'nilai_angka_akhir' => 'decimal:2',
        'bobot_mutu_akhir' => 'decimal:2',
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

    public function dosenPembimbing1(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_pembimbing_1_id');
    }

    public function dosenPembimbing2(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_pembimbing_2_id');
    }

    public function bimbingans(): HasMany
    {
        return $this->hasMany(TugasAkhirBimbingan::class, 'tugas_akhir_id');
    }

    public function sidangs(): HasMany
    {
        return $this->hasMany(TugasAkhirSidang::class, 'tugas_akhir_id');
    }
}
