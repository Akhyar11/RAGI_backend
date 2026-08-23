<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\KuesionerSpmb;
use App\Models\Spmb\JawabanKuesionerSpmb;

class PertanyaanKuesionerSpmb extends Model
{
    use HasFactory;

    protected $table = 'spmb_pertanyaan_kuesioner';
    
    public $timestamps = false;

    protected $fillable = [
        'kuesioner_id',
        'pertanyaan',
        'tipe',
        'opsi_jawaban',
        'is_required',
        'urutan',
    ];

    protected $casts = [
        'opsi_jawaban' => 'array',
        'is_required' => 'boolean',
    ];

    public function kuesionerSpmb()
    {
        return $this->belongsTo(KuesionerSpmb::class, 'kuesioner_id');
    }

    public function jawabanKuesionerSpmb()
    {
        return $this->hasMany(JawabanKuesionerSpmb::class, 'pertanyaan_id');
    }
}
