<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spmb_pendaftaran_calon_mhs')) {
            Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table): void {
                if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kewarganegaraan_id')) {
                    $table->foreignId('kewarganegaraan_id')->nullable()->after('kewarganegaraan')->constrained('spmb_master_referensi')->nullOnDelete();
                }
                if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'nomor_paspor')) {
                    $table->string('nomor_paspor', 50)->nullable()->unique()->after('nik');
                }
                if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'negara_penerbit_paspor')) {
                    $table->string('negara_penerbit_paspor', 100)->nullable()->after('nomor_paspor');
                }
                if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'negara_kewarganegaraan')) {
                    $table->string('negara_kewarganegaraan', 100)->nullable()->after('negara_penerbit_paspor');
                }
                if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kode_negara_kewarganegaraan')) {
                    $table->string('kode_negara_kewarganegaraan', 2)->nullable()->after('negara_kewarganegaraan');
                }
                if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'negara_domisili')) {
                    $table->string('negara_domisili', 100)->nullable()->after('alamat');
                }
            });
        }

        if (Schema::hasTable('siakad_mahasiswa')) {
            Schema::table('siakad_mahasiswa', function (Blueprint $table): void {
                if (! Schema::hasColumn('siakad_mahasiswa', 'nomor_paspor')) {
                    $table->string('nomor_paspor', 50)->nullable()->after('nik');
                }
                if (! Schema::hasColumn('siakad_mahasiswa', 'negara_penerbit_paspor')) {
                    $table->string('negara_penerbit_paspor', 100)->nullable()->after('nomor_paspor');
                }
                if (! Schema::hasColumn('siakad_mahasiswa', 'negara_kewarganegaraan')) {
                    $table->string('negara_kewarganegaraan', 100)->nullable()->after('negara_penerbit_paspor');
                }
                if (! Schema::hasColumn('siakad_mahasiswa', 'kode_negara_kewarganegaraan')) {
                    $table->string('kode_negara_kewarganegaraan', 2)->nullable()->after('negara_kewarganegaraan');
                }
                if (! Schema::hasColumn('siakad_mahasiswa', 'kewarganegaraan')) {
                    $table->string('kewarganegaraan', 100)->nullable()->after('agama');
                }
                if (! Schema::hasColumn('siakad_mahasiswa', 'negara_domisili')) {
                    $table->string('negara_domisili', 100)->nullable()->after('alamat');
                }
            });
        }

        if (! Schema::hasTable('spmb_master_referensi')) {
            return;
        }

        $now = now();
        foreach ([
            ['kode' => 'WNI', 'nama' => 'Warga Negara Indonesia'],
            ['kode' => 'WNA', 'nama' => 'Warga Negara Asing'],
        ] as $index => $reference) {
            DB::table('spmb_master_referensi')->updateOrInsert(
                ['modul' => 'global', 'tipe' => 'kewarganegaraan', 'kode' => $reference['kode']],
                [
                    'nama' => $reference['nama'],
                    'urutan' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $wniId = DB::table('spmb_master_referensi')
            ->where('tipe', 'kewarganegaraan')
            ->where('kode', 'WNI')
            ->value('id');

        if ($wniId && Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kewarganegaraan_id')) {
            DB::table('spmb_pendaftaran_calon_mhs')
                ->whereNull('kewarganegaraan_id')
                ->where(function ($query): void {
                    $query->whereNull('kewarganegaraan')
                        ->orWhereIn(DB::raw('UPPER(kewarganegaraan)'), ['WNI', 'INDONESIA', 'WARGA NEGARA INDONESIA']);
                })
                ->update(['kewarganegaraan_id' => $wniId]);
        }

        if (Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kode_negara_kewarganegaraan')) {
            DB::table('spmb_pendaftaran_calon_mhs')
                ->whereNull('kode_negara_kewarganegaraan')
                ->where(function ($query): void {
                    $query->whereNull('kewarganegaraan')
                        ->orWhereIn(DB::raw('UPPER(kewarganegaraan)'), ['WNI', 'INDONESIA', 'WARGA NEGARA INDONESIA']);
                })
                ->update(['kode_negara_kewarganegaraan' => 'ID']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('siakad_mahasiswa')) {
            Schema::table('siakad_mahasiswa', function (Blueprint $table): void {
                foreach (['negara_domisili', 'kode_negara_kewarganegaraan', 'negara_kewarganegaraan', 'kewarganegaraan', 'negara_penerbit_paspor', 'nomor_paspor'] as $column) {
                    if (Schema::hasColumn('siakad_mahasiswa', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('spmb_pendaftaran_calon_mhs')) {
            Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table): void {
                if (Schema::hasColumn('spmb_pendaftaran_calon_mhs', 'kewarganegaraan_id')) {
                    $table->dropConstrainedForeignId('kewarganegaraan_id');
                }
                foreach (['negara_domisili', 'kode_negara_kewarganegaraan', 'negara_kewarganegaraan', 'negara_penerbit_paspor', 'nomor_paspor'] as $column) {
                    if (Schema::hasColumn('spmb_pendaftaran_calon_mhs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // Keep reference items on rollback: they may already be used by registrations.
    }
};
