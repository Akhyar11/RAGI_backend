<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispensasiTagihan extends Model
{
    use HasFactory;

    protected $table = 'dispensasi_tagihan';

    protected $fillable = [
        'tagihan_id',
        'mahasiswa_id',
        'tipe_dispensasi',
        'jatuh_tempo_baru',
        'jumlah_cicilan',
        'nominal_per_cicilan',
        'alasan',
        'dokumen_pendukung',
        'status',
        'diajukan_oleh',
        'disetujui_oleh',
        'tanggal_persetujuan',
        'catatan_pimpinan',
    ];

    protected $casts = [
        'jatuh_tempo_baru' => 'date',
        'nominal_per_cicilan' => 'decimal:2',
        'tanggal_persetujuan' => 'datetime',
    ];

    public function tagihan()
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }
}
