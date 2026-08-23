<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanPencairanKas extends Model
{
    use HasFactory;

    protected $table = 'sikeu_pengajuan_pencairan_kas';

    protected $fillable = [
        'nomor_pengajuan',
        'unit_kerja_id',
        'unit_kas_id',
        'pemohon_id',
        'judul_pengajuan',
        'deskripsi',
        'nominal_diajukan',
        'nominal_disetujui',
        'jenis_pengajuan',
        'file_lampiran',
        'status',
        'approved_pimpinan_by',
        'approved_pimpinan_at',
        'approved_keuangan_by',
        'approved_keuangan_at',
    ];

    protected $casts = [
        'nominal_diajukan' => 'decimal:2',
        'nominal_disetujui' => 'decimal:2',
        'approved_pimpinan_at' => 'datetime',
        'approved_keuangan_at' => 'datetime',
    ];

    public function unitKas()
    {
        return $this->belongsTo(UnitKas::class, 'unit_kas_id');
    }

    public function historyApproval()
    {
        return $this->hasMany(ApprovalHistoryPencairan::class, 'pengajuan_id');
    }
}
