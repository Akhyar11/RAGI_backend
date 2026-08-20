<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class NilaiKomponenMahasiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_nilai_komponen_mhs';

    protected $fillable = [
        'krs_detail_id',
        'komponen_penilaian_id',
        'nilai_angka',
        'catatan_feedback',
        'diinput_oleh',
    ];

    protected $casts = [
        'nilai_angka' => 'decimal:2',
    ];

    public function krsDetail()
    {
        return $this->belongsTo(KrsDetail::class, 'krs_detail_id');
    }

    public function komponenPenilaian()
    {
        return $this->belongsTo(KomponenPenilaian::class, 'komponen_penilaian_id');
    }

    public function penginput()
    {
        return $this->belongsTo(User::class, 'diinput_oleh');
    }
}
