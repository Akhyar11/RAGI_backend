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

    // ── Alur pencairan (selaras Pengajuan Operasional SIKEU) ─────────
    public const STATUS_PENDING_KEUANGAN = 'pending_keuangan';
    public const STATUS_PENDING_DIREKTUR = 'pending_direktur';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DICAIRKAN = 'dicairkan';
    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'referrer_user_id',
        'referral_count',
        'total_nominal',
        'nomor_bukti',
        'generated_at',
        'keterangan',
        'status',
        'verified_by',
        'verified_at',
        'approved_keuangan_by',
        'approved_keuangan_at',
        'approved_direktur_by',
        'approved_direktur_at',
        'paid_by',
        'paid_at',
        'sikeu_reference',
        'nomor_referensi_transfer',
        'bukti_transfer_path',
        'catatan_penolakan',
        'nama_bank',
        'nomor_rekening',
        'nama_pemilik_rekening',
    ];

    protected $casts = [
        'referral_count' => 'integer',
        'total_nominal' => 'decimal:2',
        'generated_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_keuangan_at' => 'datetime',
        'approved_direktur_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function approverKeuangan()
    {
        return $this->belongsTo(User::class, 'approved_keuangan_by');
    }

    public function approverDirektur()
    {
        return $this->belongsTo(User::class, 'approved_direktur_by');
    }

    public function usages()
    {
        return $this->hasMany(ReferralUsage::class, 'payout_id');
    }
}
