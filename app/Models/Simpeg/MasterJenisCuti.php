<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterJenisCuti extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_master_jenis_cuti';

    protected $fillable = [
        'nama',
        'kode',
        'tipe_durasi',
        'durasi_hari',
        'satuan',
        'lampiran_wajib',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'durasi_hari' => 'integer',
        'lampiran_wajib' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function pengajuanCuti(): HasMany
    {
        return $this->hasMany(PengajuanCuti::class, 'master_jenis_cuti_id');
    }
}
