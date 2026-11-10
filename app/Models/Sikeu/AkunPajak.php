<?php

namespace App\Models\Sikeu;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AkunPajak extends Model
{
    protected $table = 'sikeu_akun_pajak';

    protected $fillable = [
        'akun_id',
        'nama_akun_pajak',
        'updated_by',
    ];

    public function akun()
    {
        return $this->belongsTo(AkunKeuangan::class, 'akun_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
