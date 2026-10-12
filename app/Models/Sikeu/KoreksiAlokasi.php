<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KoreksiAlokasi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sikeu_koreksi_alokasi';

    protected $fillable = [
        'mahasiswa_id',
        'calon_mahasiswa_id',
        'tipe_referensi',
        'nomor_koreksi',
        'tahun_akademik_id',
        'semester',
        'alasan',
        'referensi',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'applied_at',
        'catatan_approval',
        'total_nominal_dialihkan',
        'buat_tagihan_kekurangan',
    ];

    protected $casts = [
        'semester' => 'integer',
        'total_nominal_dialihkan' => 'decimal:2',
        'buat_tagihan_kekurangan' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(KoreksiAlokasiDetail::class, 'koreksi_alokasi_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(\App\Models\Siakad\Mahasiswa::class, 'mahasiswa_id');
    }

    public function tagihanKekurangan()
    {
        return $this->hasMany(TagihanMahasiswa::class, 'koreksi_alokasi_id');
    }
}
