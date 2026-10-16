<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UangMukaKerja extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sikeu_umk';

    protected $fillable = [
        'unit_kas_id',
        'nama_penerima',
        'tanggal',
        'tanggal_selesai',
        'keterangan',
        'total',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_selesai' => 'date',
        'total' => 'decimal:2',
    ];

    public function unitKas()
    {
        return $this->belongsTo(UnitKas::class, 'unit_kas_id');
    }
}
