<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Siakad\ProgramStudi;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\DokumenPendaftaran;
use App\Models\Spmb\PembayaranSpmb;
use App\Models\Spmb\PesertaUjianSpmb;
use App\Models\Spmb\JawabanKuesionerSpmb;
use App\Models\Spmb\NilaiSeleksi;
use App\Models\Spmb\HasilSeleksi;

class PendaftaranCalonMhs extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pendaftaran_calon_mhs';

    protected $fillable = [
        'gelombang_id',
        'user_id',
        'program_studi_id',
        'program_studi_pilihan2_id',
        'no_pendaftaran',
        'nama_lengkap',
        'nik',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'kewarganegaraan',
        'alamat',
        'asal_sekolah',
        'jurusan_sekolah',
        'nilai_rata_rapor',
        'tahun_lulus',
        'nama_wali',
        'telepon_wali',
        'status',
        'status_pembayaran',
        'catatan_verifikasi',
        'diverifikasi_oleh',
        'diverifikasi_at',
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
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function programStudiPilihan2()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_pilihan2_id');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function dokumenPendaftaran()
    {
        return $this->hasMany(DokumenPendaftaran::class, 'pendaftaran_id');
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
