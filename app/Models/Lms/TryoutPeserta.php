<?php

namespace App\Models\Lms;

use App\Models\Siakad\Mahasiswa;
use Illuminate\Database\Eloquent\Model;

class TryoutPeserta extends Model
{
    protected $table = 'lms_tryout_peserta';

    protected $fillable = [
        'quiz_id',
        'mahasiswa_id',
        'ditambah_oleh',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }
}
