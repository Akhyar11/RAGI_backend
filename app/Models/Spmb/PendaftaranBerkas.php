<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendaftaranBerkas extends Model
{
    use HasFactory;

    protected $table = 'pendaftaran_berkas';

    protected $fillable = [
        'pendaftaran_id',
        'jenis_berkas',
        'file_path',
        'is_verified',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }
}
