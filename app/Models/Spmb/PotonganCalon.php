<?php

namespace App\Models\Spmb;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PotonganCalon extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_potongan_calon';

    protected $fillable = [
        'pendaftaran_id',
        'komponen_biaya_id',
        'nama_komponen',
        'nama_potongan',
        'tipe_potongan',
        'nilai_potongan',
        'tahap',
        'nomor_sk',
        'keterangan',
        'berlaku_mulai',
        'berlaku_sampai',
        'status',
        'dibuat_oleh',
        'disetujui_oleh',
    ];

    protected $casts = [
        'nilai_potongan' => 'decimal:2',
        'berlaku_mulai' => 'date',
        'berlaku_sampai' => 'date',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function komponenBiaya()
    {
        return $this->belongsTo(MasterKomponenBiaya::class, 'komponen_biaya_id');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function penyetuju()
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    public function scopeUntukTahap($query, string $tahap)
    {
        return $query->where(function ($q) use ($tahap) {
            $q->where('tahap', $tahap)->orWhere('tahap', 'keduanya');
        });
    }

    public function scopeBerlaku($query)
    {
        $today = now()->toDateString();

        return $query
            ->where(function ($q) use ($today) {
                $q->whereNull('berlaku_mulai')->orWhere('berlaku_mulai', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $today);
            });
    }
}
