<?php

namespace App\Models\Sippm;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PengumumanHibah extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sippm_pengumuman_hibah';

    protected $fillable = [
        'periode_id',
        'nomor_surat',
        'tgl_surat',
        'hal_surat',
        'tahun_anggaran',
        'tujuan_yth',
        'kualifikasi_dosen',
        'kategori_pendanaan',
        'tgl_buka_proposal',
        'tgl_tutup_proposal',
        'nama_ketua_uppm',
        'nik_ketua_uppm',
        'nama_direktur',
        'nik_direktur',
        'file_draft_pdf_path',
        'file_signed_pdf_path',
        'file_template_mitra_indo_path',
        'file_template_mitra_intl_path',
        'status',
        'lampiran_jadwal',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'tgl_surat' => 'date:Y-m-d',
        'tgl_buka_proposal' => 'date:Y-m-d',
        'tgl_tutup_proposal' => 'date:Y-m-d',
        'published_at' => 'datetime:Y-m-d H:i:s',
        'lampiran_jadwal' => 'array',
    ];

    public function periode()
    {
        return $this->belongsTo(PeriodeHibah::class, 'periode_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
