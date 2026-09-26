<?php

namespace App\Models\Spmb;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mapping nominal reward referral per role untuk sebuah komponen biaya SPMB.
 */
class KomponenBiayaRoleReward extends Model
{
    use HasFactory;

    protected $table = 'spmb_komponen_biaya_role_reward';

    protected $fillable = [
        'komponen_biaya_id',
        'role_id',
        'nominal',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'role_id' => 'integer',
    ];

    public function komponenBiaya()
    {
        return $this->belongsTo(MasterKomponenBiaya::class, 'komponen_biaya_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function scopeForRole($query, int $roleId)
    {
        return $query->where('role_id', $roleId);
    }
}
