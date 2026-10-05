<?php

namespace App\Models\Lms;

use App\Models\Siakad\Kelas;
use App\Models\Siakad\Pertemuan;
use Illuminate\Database\Eloquent\Model;

class ForumTopik extends Model
{
    protected $table = 'lms_forum_topik';

    protected $fillable = [
        'kelas_id',
        'pertemuan_id',
        'judul',
        'dibuat_oleh',
        'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function pertemuan()
    {
        return $this->belongsTo(Pertemuan::class, 'pertemuan_id');
    }

    public function posts()
    {
        return $this->hasMany(ForumPost::class, 'topik_id')->orderBy('created_at');
    }
}
