<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan hash tanda tangan digital dispensasi agar dapat
     * diverifikasi publik via QR Code pada surat bukti.
     */
    public function up(): void
    {
        Schema::table('sikeu_dispensasi_tagihan', function (Blueprint $table) {
            $table->string('signature_hash', 64)->nullable()->unique()->after('catatan_pimpinan');
        });

        // Backfill dispensasi yang sudah disetujui agar QR surat lama tetap valid
        $secretKey = config('app.key') ?: 'sikeu-signature-salt';
        DB::table('sikeu_dispensasi_tagihan')
            ->where('status', 'approved')
            ->whereNull('signature_hash')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($secretKey) {
                foreach ($rows as $row) {
                    $raw = hash_hmac('sha256', "DISP-{$row->id}-{$row->mahasiswa_id}-{$row->jatuh_tempo_baru}", $secretKey);
                    DB::table('sikeu_dispensasi_tagihan')
                        ->where('id', $row->id)
                        ->update(['signature_hash' => 'SIG-DISP-' . strtoupper(substr($raw, 0, 16))]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('sikeu_dispensasi_tagihan', function (Blueprint $table) {
            $table->dropUnique(['signature_hash']);
            $table->dropColumn('signature_hash');
        });
    }
};
