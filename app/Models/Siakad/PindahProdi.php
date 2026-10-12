<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PindahProdi extends Model
{
    protected $table = 'siakad_mahasiswa_pindah_prodi';

    /** Hasil cek keuangan (closed-set domain, terpusat). */
    public const HASIL_LAYAK = 'layak';
    public const HASIL_ADA_TUNGGAKAN = 'ada_tunggakan';
    public const HASIL_CEK_KEUANGAN = [self::HASIL_LAYAK, self::HASIL_ADA_TUNGGAKAN];

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
        'dicek_keuangan_oleh',
        'dicek_keuangan_pada',
        'hasil_cek_keuangan',
        'catatan_keuangan',
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
        'dicek_keuangan_pada' => 'datetime',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function prodiLama()
    {
        return $this->belongsTo(ProgramStudi::class, 'old_program_studi_id');
    }

    public function prodiBaru()
    {
        return $this->belongsTo(ProgramStudi::class, 'new_program_studi_id');
    }
}
