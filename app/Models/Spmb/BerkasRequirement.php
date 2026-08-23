<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BerkasRequirement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_berkas_requirement';

    protected $fillable = [
        'jalur_masuk_id',
        'jenis_dokumen',
        'label',
        'wajib',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'wajib' => 'boolean',
        'urutan' => 'integer',
        'is_active' => 'boolean',
    ];

    public function jalurMasuk()
    {
        return $this->belongsTo(JalurMasuk::class, 'jalur_masuk_id');
    }

    public function dokumenPendaftaran()
    {
        return $this->hasMany(DokumenPendaftaran::class, 'berkas_requirement_id');
    }
}
