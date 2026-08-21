<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\DokumenPendaftaran;
use App\Models\Spmb\PembayaranSpmb;
use App\Models\Spmb\PesertaUjianSpmb;
use App\Models\Spmb\JawabanKuesionerSpmb;
use App\Models\Spmb\NilaiSeleksi;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\SpmbStatusHistory;

class PendaftaranCalonMhs extends Model
{
    use HasFactory, SoftDeletes;

    // ── Nilai status pendaftaran (kolom string, bukan enum) ──────────
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_LULUS_ADMINISTRASI = 'lulus_administrasi';
    public const STATUS_GAGAL_ADMINISTRASI = 'gagal_administrasi';
    public const STATUS_MAHASISWA_BARU = 'mahasiswa_baru';

    // ── Nilai status pembayaran ──────────────────────────────────────
    public const STATUS_PEMBAYARAN_BELUM = 'belum_bayar';
    public const STATUS_PEMBAYARAN_SEBAGIAN = 'sebagian';
    public const STATUS_PEMBAYARAN_LUNAS = 'lunas';
    public const STATUS_PEMBAYARAN_GRATIS = 'gratis';

    protected $table = 'pendaftaran_calon_mhs';

    protected $fillable = [
        'gelombang_id',
        'user_id',
        'program_studi_id',
        'program_studi_pilihan2_id',
        'master_tipe_jalur_id',
        'master_jalur_kelas_id',
        'info_daftar',
        'ket_info_daftar',
        'no_pendaftaran',
        'nim',
        'nama_lengkap',
        'nik',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'status_sipil',
        'kewarganegaraan',
        'no_hp',
        'alamat',
        'provinsi',
        'kota_kabupaten',
        'kecamatan',
        'kode_pos',
        'asal_sekolah',
        'alamat_sekolah',
        'jurusan_sekolah',
        'nilai_rata_rapor',
        'tahun_lulus',
        'npsn_sekolah',
        'nama_ayah',
        'pekerjaan_ayah',
        'nama_ibu',
        'pekerjaan_ibu',
        'penghasilan_ortu',
        'nama_ortu',
        'alamat_ortu',
        'telp_ortu',
        'nama_wali',
        'telepon_wali',
        'status',
        'status_pembayaran',
        'catatan_verifikasi',
        'diverifikasi_oleh',
        'diverifikasi_at',
        'asal_lulusan',
        'asal_pt',
        'jenis_pt',
        'alamat_pt',
        'jenjang_pt',
        'progdi_pt',
        'ipk_pt',
        'nim_pt',
        'tahun_lulus_pt',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'diverifikasi_at' => 'datetime',
        'nilai_rata_rapor' => 'decimal:2',
    ];

    public function gelombangPenerimaan()
    {
        return $this->belongsTo(GelombangPenerimaan::class, 'gelombang_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function programStudiPilihan2()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_pilihan2_id');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function tipe_jalur()
    {
        return $this->belongsTo(App\Models\Spmb\MasterTipeJalur::class, 'master_tipe_jalur_id');
    }

    public function jalur_kelas()
    {
        return $this->belongsTo(App\Models\Sikeu\MasterJalurKelas::class, 'master_jalur_kelas_id');
    }

    public function dokumen_pendaftaran()
    {
        return $this->hasMany(PendaftaranBerkas::class, 'pendaftaran_id');
    }

    public function pembayaranSpmb()
    {
        return $this->hasOne(PembayaranSpmb::class, 'pendaftaran_id');
    }

    public function pesertaUjianSpmb()
    {
        return $this->hasMany(PesertaUjianSpmb::class, 'pendaftaran_id');
    }

    public function jawabanKuesionerSpmb()
    {
        return $this->hasMany(JawabanKuesionerSpmb::class, 'pendaftaran_id');
    }

    public function nilaiSeleksi()
    {
        return $this->hasMany(NilaiSeleksi::class, 'pendaftaran_id');
    }

    public function hasilSeleksi()
    {
        return $this->hasOne(HasilSeleksi::class, 'pendaftaran_id');
    }
}
