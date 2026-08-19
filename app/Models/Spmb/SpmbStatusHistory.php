<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class SpmbStatusHistory extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'spmb_status_history';

    protected $fillable = [
        'pendaftaran_id',
        'status_lama',
        'status_baru',
        'actor_id',
        'catatan',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
