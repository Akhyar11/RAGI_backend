<?php

namespace App\Models\Spmb;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralUsage extends Model
{
    use HasFactory, SoftDeletes;

    // ── Nilai status penggunaan kode referral (internal workflow) ─────
    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_REWARDED = 'rewarded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'spmb_referral_usages';

    protected $fillable = [
        'pendaftaran_id',
        'referee_user_id',
        'referrer_user_id',
        'referral_code',
        'status',
        'qualified_at',
        'rewarded_at',
        'reward_ref_type',
        'reward_ref_id',
        'payout_id',
        'keterangan',
    ];

    protected $casts = [
        'qualified_at' => 'datetime',
        'rewarded_at' => 'datetime',
        'reward_ref_id' => 'integer',
        'payout_id' => 'integer',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function payout()
    {
        return $this->belongsTo(PayoutReferral::class, 'payout_id');
    }

    public function referee()
    {
        return $this->belongsTo(User::class, 'referee_user_id');
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function scopeForReferrer($query, int $referrerUserId)
    {
        return $query->where('referrer_user_id', $referrerUserId);
    }
}
