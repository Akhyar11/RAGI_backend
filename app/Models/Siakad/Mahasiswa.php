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
        'nisn',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'rt',
        'rw',
        'dusun',
        'kelurahan',
        'kecamatan',
        'kota',
        'provinsi',
        'kode_pos',
        'jenis_tinggal',
        'alat_transportasi',
        'telepon',
        'email',
        'nama_ibu_kandung',
        'nik_ibu',
        'nama_ayah',
        'nik_ayah',
        'nama_wali',
        'angkatan',
        'tanggal_masuk',
        'jalur_masuk',
        'jenis_pendaftaran',
        'status',
        'dosen_wali_id',
        'id_feeder',
        'id_feeder_biodata',
        'id_feeder_riwayat',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_masuk' => 'date',
        'angkatan' => 'integer',
    ];

    protected $appends = ['ipk'];

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

    public function getIpkAttribute()
    {
        $latestKhs = $this->khs()->latest('id')->first();
        if ($latestKhs) {
            return (float) $latestKhs->ipk;
        }

        if ($this->konversi_id) {
            $konvTransfer = $this->konversiTransfer;
            if ($konvTransfer && $konvTransfer->status === 'disetujui' && $konvTransfer->details && count($konvTransfer->details) > 0) {
                $totalSks = 0;
                $totalMutu = 0;
                foreach ($konvTransfer->details as $konv) {
                    $mk = $konv->mataKuliahDiakui;
                    $sks = $mk ? $mk->total_sks : $konv->sks_asal;
                    $huruf = $konv->nilai_huruf_asal;
                    $mutu = 4.0;
                    if ($huruf === 'A-') $mutu = 3.75;
                    elseif ($huruf === 'B+') $mutu = 3.25;
                    elseif ($huruf === 'B') $mutu = 3.00;
                    elseif ($huruf === 'B-') $mutu = 2.75;
                    elseif ($huruf === 'C+') $mutu = 2.25;
                    elseif ($huruf === 'C') $mutu = 2.00;

                    $totalSks += $sks;
                    $totalMutu += ($mutu * $sks);
                }
                return $totalSks > 0 ? round($totalMutu / $totalSks, 2) : 0.00;
            }
        }

        return 0.00;
    }
}
