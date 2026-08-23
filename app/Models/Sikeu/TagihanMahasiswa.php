<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TagihanMahasiswa extends Model
{
    use HasFactory;

    protected $table = 'sikeu_tagihan_mahasiswa';

    protected $fillable = [
        'mahasiswa_id',
        'calon_mahasiswa_id',
        'tipe_referensi',
        'tahun_akademik_id',
        'nomor_tagihan',
        'total_tagihan',
        'total_potongan',
        'total_denda',
        'total_bayar',
        'status',
        'requires_approval',
        'status_approval',
        'disetujui_oleh',
        'tanggal_approval',
        'catatan_approval',
        'source_system',
        'jatuh_tempo',
    ];

    protected $casts = [
        'total_tagihan' => 'decimal:2',
        'total_potongan' => 'decimal:2',
        'total_denda' => 'decimal:2',
        'total_bayar' => 'decimal:2',
        'requires_approval' => 'boolean',
        'tanggal_approval' => 'datetime',
        'jatuh_tempo' => 'date',
    ];

    public function detailTagihan()
    {
        return $this->hasMany(DetailTagihan::class, 'tagihan_id');
    }

    public function details()
    {
        return $this->hasMany(DetailTagihan::class, 'tagihan_id');
    }

    public function potonganTagihan()
    {
        return $this->hasMany(PotonganTagihan::class, 'tagihan_id');
    }

    public function dendaTagihan()
    {
        return $this->hasMany(DendaTagihan::class, 'tagihan_id');
    }

    public function virtualAccount()
    {
        return $this->hasOne(VirtualAccount::class, 'tagihan_id');
    }

    public function virtualAccounts()
    {
        return $this->hasMany(VirtualAccount::class, 'tagihan_id');
    }

    public function pembayarans()
    {
        return $this->hasMany(Pembayaran::class, 'tagihan_id');
    }

    public function dispensasi()
    {
        return $this->hasMany(DispensasiTagihan::class, 'tagihan_id');
    }

    public function dispensasis()
    {
        return $this->hasMany(DispensasiTagihan::class, 'tagihan_id');
    }
}
