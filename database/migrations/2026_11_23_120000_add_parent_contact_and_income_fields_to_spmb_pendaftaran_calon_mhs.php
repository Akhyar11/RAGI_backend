<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table): void {
            foreach ([
                'telepon_ayah' => 30,
                'penghasilan_ayah' => 100,
                'telepon_ibu' => 30,
                'penghasilan_ibu' => 100,
                'pekerjaan_wali' => 100,
                'penghasilan_wali' => 100,
            ] as $column => $length) {
                if (! Schema::hasColumn('spmb_pendaftaran_calon_mhs', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('spmb_pendaftaran_calon_mhs', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                'telepon_ayah',
                'penghasilan_ayah',
                'telepon_ibu',
                'penghasilan_ibu',
                'pekerjaan_wali',
                'penghasilan_wali',
            ], static fn (string $column): bool => Schema::hasColumn('spmb_pendaftaran_calon_mhs', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
