<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lacak porsi pembayaran per komponen rincian (FIFO) agar kekurangan
     * bayar dapat direkap per komponen, bukan hanya total tagihan.
     * Backfill: distribusikan total_bayar eksisting secara FIFO per detail.
     */
    public function up(): void
    {
        Schema::table('sikeu_detail_tagihan', function (Blueprint $table) {
            $table->decimal('terbayar', 15, 2)->default(0)->after('nominal_bersih');
        });

        DB::table('sikeu_tagihan_mahasiswa')
            ->where('total_bayar', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($tagihans) {
                foreach ($tagihans as $tagihan) {
                    $remaining = (float) $tagihan->total_bayar;
                    $details = DB::table('sikeu_detail_tagihan')
                        ->where('tagihan_id', $tagihan->id)
                        ->orderBy('id')
                        ->get(['id', 'nominal_bersih', 'terbayar']);
                    foreach ($details as $detail) {
                        if ($remaining <= 0) {
                            break;
                        }
                        $capacity = max(0, (float) $detail->nominal_bersih - (float) $detail->terbayar);
                        $fill = min($remaining, $capacity);
                        if ($fill > 0) {
                            DB::table('sikeu_detail_tagihan')
                                ->where('id', $detail->id)
                                ->update(['terbayar' => (float) $detail->terbayar + $fill]);
                            $remaining -= $fill;
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('sikeu_detail_tagihan', function (Blueprint $table) {
            $table->dropColumn('terbayar');
        });
    }
};
