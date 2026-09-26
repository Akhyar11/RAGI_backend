<?php

namespace App\Models\Spmb;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bukti pencairan (payout) reward referral per referrer.
 */
class PayoutReferral extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_referral_payouts';

    protected $fillable = [
        'referrer_user_id',
        'referral_count',
        'total_nominal',
        'nomor_bukti',
        'generated_at',
        'keterangan',
    ];

    protected $casts = [
        'referral_count' => 'integer',
        'total_nominal' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function usages()
    {
        return $this->hasMany(ReferralUsage::class, 'payout_id');
    }
}
