<?php

namespace App\Policies\Spmb;

use App\Models\Spmb\PayoutReferral;
use App\Models\User;

class PayoutReferralPolicy
{
    /**
     * Pengguna hanya boleh melihat/mengunduh payout miliknya sendiri.
     * Panitia SPMB (permission laporan) dan admin keuangan
     * (verifikator invoice) boleh melihat semua.
     */
    public function view(User $user, PayoutReferral $payout): bool
    {
        if ((int) $payout->referrer_user_id === (int) $user->id) {
            return true;
        }

        if ($user->can('spmb.laporan.read')) {
            return true;
        }

        return $user->can('sikeu.pengeluaran.read');
    }
}
