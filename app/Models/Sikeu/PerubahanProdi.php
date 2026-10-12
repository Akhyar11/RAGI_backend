<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerubahanProdi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sikeu_mahasiswa_perubahan_prodi';

    protected $fillable = [
        'mahasiswa_id',
        'old_program_studi_id',
        'new_program_studi_id',
        'tahun_akademik_id',
        'semester_efektif',
        'tanggal_efektif',
        'jenis_pendaftaran_lama',
        'jenis_pendaftaran_baru',
        'alasan',
        'referensi_surat',
        'dokumen_path',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'applied_by',
        'applied_at',
        'catatan_approval',
    ];

    protected $casts = [
        'semester_efektif' => 'integer',
        'tanggal_efektif' => 'date',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(\App\Models\Siakad\Mahasiswa::class, 'mahasiswa_id');
    }
}
