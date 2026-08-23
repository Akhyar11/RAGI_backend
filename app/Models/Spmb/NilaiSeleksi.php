<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;

class NilaiSeleksi extends Model
{
    use HasFactory;

    protected $table = 'spmb_nilai_seleksi';

    protected $fillable = [
        'pendaftaran_id',
        'komponen_nilai',
        'nilai',
        'catatan',
        'dinilai_oleh',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function penilai()
    {
        return $this->belongsTo(User::class, 'dinilai_oleh');
    }
}
