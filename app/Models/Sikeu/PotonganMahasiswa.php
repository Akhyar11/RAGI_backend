<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PotonganMahasiswa extends Model
{
    use HasFactory;

    protected $table = 'sikeu_potongan_mahasiswa';

    protected $fillable = [
        'mahasiswa_id',
        'nim',
        'nama_mahasiswa',
        'nama_potongan',
        'tipe_potongan',
        'nilai_potongan',
        'master_biaya_id',
        'semester',
        'tahun_akademik',
        'berlaku_mulai',
        'berlaku_sampai',
        'nomor_sk',
        'keterangan',
        'status',
        'diinput_oleh',
    ];

    protected $casts = [
        'nilai_potongan' => 'decimal:2',
        'semester' => 'integer',
        'berlaku_mulai' => 'date',
        'berlaku_sampai' => 'date',
    ];

    public function masterBiaya()
    {
        return $this->belongsTo(MasterBiaya::class, 'master_biaya_id');
    }

    public function inputter()
    {
        return $this->belongsTo(User::class, 'diinput_oleh');
    }
}
