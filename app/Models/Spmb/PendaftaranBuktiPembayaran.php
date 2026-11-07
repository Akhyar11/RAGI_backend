<?php

namespace App\Models\Spmb;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PendaftaranBuktiPembayaran extends Model
{
    public const STATUS_MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';

    protected $table = 'spmb_pendaftaran_bukti_pembayaran';

    protected $fillable = [
        'pendaftaran_id',
        'file_path',
        'original_name',
        'status',
        'diunggah_oleh',
        'diverifikasi_oleh',
        'diverifikasi_at',
        'catatan_verifikasi',
    ];

    protected $hidden = ['file_path'];

    protected $casts = ['diverifikasi_at' => 'datetime'];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function pengunggah()
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
