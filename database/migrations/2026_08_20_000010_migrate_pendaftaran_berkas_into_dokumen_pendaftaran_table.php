<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gabungkan data pendaftaran_berkas ke dokumen_pendaftaran, lalu hapus tabel pendaftaran_berkas.
     * Migrasi 000008 telah menambahkan berkas_requirement_id/verified_by/verified_at ke dokumen_pendaftaran
     * dan migrasi 000009 telah mengubah jenis_dokumen menjadi string bebas.
     */
    public function up(): void
    {
        DB::table('pendaftaran_berkas')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $insert = [];
                $now = now();
                foreach ($rows as $row) {
                    $insert[] = [
                        'pendaftaran_id' => $row->pendaftaran_id,
                        'berkas_requirement_id' => null,
                        'jenis_dokumen' => $row->jenis_berkas,
                        'file_path' => $row->file_path,
                        'is_verified' => (bool) $row->is_verified,
                        'verified_by' => null,
                        'verified_at' => null,
                        'catatan' => null,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ];
                }

                if (! empty($insert)) {
                    DB::table('dokumen_pendaftaran')->insert($insert);
                }
            }, 'id', 'id');

        Schema::dropIfExists('pendaftaran_berkas');
    }

    public function down(): void
    {
        Schema::create('pendaftaran_berkas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->string('jenis_berkas'); // foto, ijazah, kk, rapor
            $table->string('file_path');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });

        DB::table('dokumen_pendaftaran')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $insert = [];
                foreach ($rows as $row) {
                    $insert[] = [
                        'pendaftaran_id' => $row->pendaftaran_id,
                        'jenis_berkas' => $row->jenis_dokumen,
                        'file_path' => $row->file_path,
                        'is_verified' => (bool) $row->is_verified,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                if (! empty($insert)) {
                    DB::table('pendaftaran_berkas')->insert($insert);
                }
            }, 'id', 'id');
    }
};
