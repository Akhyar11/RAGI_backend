<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MateriFile extends Model
{
    use HasFactory;

    protected $table = 'lms_materi_file';

    protected $fillable = [
        'materi_id',
        'nama_file',
        'file_path',
        'disk',
        'mime_type',
        'ukuran_bytes',
    ];

    protected $casts = [
        'ukuran_bytes' => 'integer',
    ];

    public function materi()
    {
        return $this->belongsTo(MateriPertemuan::class, 'materi_id');
    }
}
