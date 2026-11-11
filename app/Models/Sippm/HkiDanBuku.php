<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Simpeg\Pegawai;

class HkiDanBuku extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sippm_hki_dan_buku';

    public const KATEGORI_MAP = [
        'paten' => 'paten',
        'paten_sederhana' => 'paten',
        'hak_cipta' => 'hak_cipta',
        'merek' => 'hak_cipta',
        'prototype' => 'hak_cipta',
        'desain_industri' => 'desain_industri',
        'buku_ajar' => 'buku_ajar',
    ];

    protected $fillable = [
        'proposal_id',
        'pegawai_id',
        'jenis_luaran',
        'judul',
        'nomor_pencatatan_isbn',
        'penerbit_lembaga',
        'tgl_terbit_catat',
        'file_sertifikat_buku',
        'is_verified_lppm',
    ];

    protected $casts = [
        'tgl_terbit_catat' => 'date:Y-m-d',
        'is_verified_lppm' => 'boolean',
    ];

    public function proposal()
    {
        return $this->belongsTo(ProposalKegiatan::class, 'proposal_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
