<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\PertanyaanKuesionerSpmb;

class JawabanKuesionerSpmb extends Model
{
    use HasFactory;

    protected $table = 'jawaban_kuesioner_spmb';
    
    const UPDATED_AT = null;

    protected $fillable = [
        'pendaftaran_id',
        'pertanyaan_id',
        'jawaban',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function pertanyaanKuesionerSpmb()
    {
        return $this->belongsTo(PertanyaanKuesionerSpmb::class, 'pertanyaan_id');
    }
}
