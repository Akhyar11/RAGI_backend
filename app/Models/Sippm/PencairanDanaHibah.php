<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PencairanDanaHibah extends Model
{
    use HasFactory;

    protected $table = 'pencairan_dana_hibah';

    protected $fillable = [
        'kontrak_id',
        'termin_ke',
        'persen_pencairan',
        'nominal',
        'status',
        'status_termin',
        'tgl_cair',
        'bukti_transfer',
        'catatan_verifikasi',
    ];

    protected $casts = [
        'termin_ke' => 'integer',
        'persen_pencairan' => 'decimal:2',
        'nominal' => 'decimal:2',
        'tgl_cair' => 'date:Y-m-d',
    ];

    public function kontrak()
    {
        return $this->belongsTo(KontrakKegiatan::class, 'kontrak_id');
    }
}
