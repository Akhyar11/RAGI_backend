<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalHistoryPencairan extends Model
{
    use HasFactory;

    protected $table = 'approval_history_pencairan';

    protected $fillable = [
        'pengajuan_id',
        'user_id',
        'role_approver',
        'status_action',
        'catatan',
    ];

    public function pengajuan()
    {
        return $this->belongsTo(PengajuanPencairanKas::class, 'pengajuan_id');
    }
}
