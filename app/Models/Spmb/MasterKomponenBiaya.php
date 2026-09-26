<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterKomponenBiaya extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_master_komponen_biaya';

    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'tipe_potongan',
        'is_referral_reward',
        'urutan',
        'is_active',
        'keterangan',
    ];

    protected $casts = [
        'tipe_potongan' => 'boolean',
        'is_referral_reward' => 'boolean',
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(MasterBiayaItem::class, 'komponen_biaya_id');
    }

    public function roleRewards()
    {
        return $this->hasMany(KomponenBiayaRoleReward::class, 'komponen_biaya_id');
    }

    /** Komponen yang menjadi sumber nominal reward referral. */
    public function scopeReferralReward($query)
    {
        return $query->where('is_referral_reward', true);
    }
}
