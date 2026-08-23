<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MasterTipeJalur;

class MasterTipeJalurAlur extends Model
{
    use HasFactory;

    protected $table = 'spmb_master_tipe_jalur_alur';

    protected $fillable = [
        'master_tipe_jalur_id',
        'nama_tahap',
        'urutan',
    ];

    public function masterTipeJalur()
    {
        return $this->belongsTo(MasterTipeJalur::class, 'master_tipe_jalur_id');
    }
}