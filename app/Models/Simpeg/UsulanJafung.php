<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsulanJafung extends Model
{
    use HasFactory;

    protected $table = 'simpeg_usulan_jafung';

    protected $fillable = [
        'pegawai_id',
        'jafung_asal_id',
        'jafung_tujuan_id',
        'angka_kredit_usulan',
        'status_usulan',
        'file_sk_hasil',
        'catatan_reviewer',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jafungAsal(): BelongsTo
    {
        return $this->belongsTo(JabatanFungsionalAkademik::class, 'jafung_asal_id');
    }

    public function jafungTujuan(): BelongsTo
    {
        return $this->belongsTo(JabatanFungsionalAkademik::class, 'jafung_tujuan_id');
    }
}
