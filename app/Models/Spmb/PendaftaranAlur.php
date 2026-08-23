<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PendaftaranAlur extends Model
{
    use HasFactory;

    protected $table = 'spmb_pendaftaran_alur';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'pendaftaran_id',
        'master_tipe_jalur_alur_id',
        'status', // pending, in_progress, completed, failed
        'catatan',
        'diperbarui_oleh',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function masterAlur()
    {
        return $this->belongsTo(MasterTipeJalurAlur::class, 'master_tipe_jalur_alur_id');
    }

    public function diperbaruiOleh()
    {
        return $this->belongsTo(User::class, 'diperbarui_oleh');
    }
}