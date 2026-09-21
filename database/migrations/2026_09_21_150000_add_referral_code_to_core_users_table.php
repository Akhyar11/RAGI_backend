<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('core_users', 'referral_code')) {
            Schema::table('core_users', function (Blueprint $table) {
                $table->string('referral_code', 50)->nullable()->unique()->after('email');
            });
        }

        // Generate unique referral code for all existing users
        $users = DB::table('core_users')->whereNull('referral_code')->orWhere('referral_code', '')->get();
        foreach ($users as $user) {
            do {
                $code = 'REF-' . strtoupper(Str::random(6));
            } while (DB::table('core_users')->where('referral_code', $code)->exists());

            DB::table('core_users')->where('id', $user->id)->update([
                'referral_code' => $code,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('core_users', 'referral_code')) {
            Schema::table('core_users', function (Blueprint $table) {
                $table->dropColumn('referral_code');
            });
        }
    }
};
