<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Siakad\Pertemuan;

class MateriPertemuan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_materi_pertemuan';

    protected $fillable = [
        'pertemuan_id',
        'tipe_konten_id',
        'judul',
        'deskripsi',
        'tipe_konten',
        'link_eksternal',
        'urutan',
        'is_published',
    ];

    protected $casts = [
        'tipe_konten_id' => 'integer',
        'urutan' => 'integer',
        'is_published' => 'boolean',
    ];

    public function tipeKonten()
    {
        return $this->belongsTo(\App\Models\System\MasterReferensi::class, 'tipe_konten_id');
    }

    public function pertemuan()
    {
        return $this->belongsTo(Pertemuan::class, 'pertemuan_id');
    }

    public function files()
    {
        return $this->hasMany(MateriFile::class, 'materi_id');
    }
}
