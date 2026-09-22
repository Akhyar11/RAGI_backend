<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setting koneksi H2H BTN Syariah via menu Payment Gateway Bank
     * (gateway_name = 'bsn_h2h') + CUSTID bridge per tagihan.
     *
     * - base_url / server_location: URL API & lokasi server bridge Go
     *   (pengganti env H2H_BRIDGE_URL; env tetap jadi fallback).
     * - db_host/port/name/username/password: kredensial database bridge
     *   (tabel va_billings) untuk polling sinkron terbayar.
     * - h2h_custid: CUSTID (no_pendaftaran/NIM) yang dikirim ke bridge,
     *   agar tampilan ke mahasiswa tidak menebak dari billing id.
     */
    public function up(): void
    {
        Schema::table('sikeu_payment_gateway_configs', function (Blueprint $table) {
            $table->string('base_url', 255)->nullable()->after('gateway_name');
            $table->string('server_location', 255)->nullable()->after('base_url');
            $table->string('db_host', 100)->nullable()->after('webhook_token_encrypted');
            $table->unsignedSmallInteger('db_port')->default(3306)->after('db_host');
            $table->string('db_name', 100)->nullable()->after('db_port');
            $table->string('db_username', 100)->nullable()->after('db_name');
            $table->text('db_password_encrypted')->nullable()->after('db_username');
        });

        Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
            $table->string('h2h_custid', 60)->nullable()->after('h2h_id_tagihan');
            $table->index('h2h_custid');
        });
    }

    public function down(): void
    {
        Schema::table('sikeu_tagihan_mahasiswa', function (Blueprint $table) {
            $table->dropIndex(['h2h_custid']);
            $table->dropColumn('h2h_custid');
        });

        Schema::table('sikeu_payment_gateway_configs', function (Blueprint $table) {
            $table->dropColumn([
                'base_url',
                'server_location',
                'db_host',
                'db_port',
                'db_name',
                'db_username',
                'db_password_encrypted',
            ]);
        });
    }
};
