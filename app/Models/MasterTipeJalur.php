<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\MasterTipeJalurAlur;

class MasterTipeJalur extends Model
{
    use HasFactory;

    protected $table = 'master_tipe_jalur';

    protected $fillable = [
        'kode',
        'nama',
    ];

    public function alur()
    {
        return $this->hasMany(MasterTipeJalurAlur::class, 'master_tipe_jalur_id')->orderBy('urutan', 'asc');
    }
}
