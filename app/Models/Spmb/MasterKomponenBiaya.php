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
        'payment_gateway_config_id',
        'tipe_potongan',
        'is_referral_reward',
        'is_default_master_biaya',
        'urutan',
        'is_active',
        'keterangan',
    ];

    protected $casts = [
        'tipe_potongan' => 'boolean',
        'is_referral_reward' => 'boolean',
        'is_default_master_biaya' => 'boolean',
        'is_active' => 'boolean',
        'urutan' => 'integer',
        'payment_gateway_config_id' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(MasterBiayaItem::class, 'komponen_biaya_id');
    }

    public function paymentGateway()
    {
        return $this->belongsTo(\App\Models\Sikeu\PaymentGatewayConfig::class, 'payment_gateway_config_id')
            ->select(['id', 'gateway_name', 'environment', 'is_active']);
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
