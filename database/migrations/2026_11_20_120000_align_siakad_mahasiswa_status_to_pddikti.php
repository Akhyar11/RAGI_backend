<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PDDIKTI_CODES = ['A', 'C', 'G', 'M', 'N', 'L', 'U'];

    private const TRANSITION_CODES = ['A', 'C', 'G', 'M', 'N', 'L', 'U', 'aktif', 'cuti', 'mangkir', 'dropout', 'lulus'];

    private const LEGACY_CODES = ['aktif', 'cuti', 'mangkir', 'dropout', 'lulus'];

    public function up(): void
    {
        if (! Schema::hasTable('siakad_mahasiswa') || ! Schema::hasColumn('siakad_mahasiswa', 'status')) {
            return;
        }

        Schema::table('siakad_mahasiswa', function (Blueprint $table): void {
            $table->enum('status', self::TRANSITION_CODES)->default('A')->change();
        });

        DB::table('siakad_mahasiswa')->where('status', 'aktif')->update(['status' => 'A']);
        DB::table('siakad_mahasiswa')->where('status', 'cuti')->update(['status' => 'C']);
        DB::table('siakad_mahasiswa')->whereIn('status', ['mangkir', 'dropout'])->update(['status' => 'N']);
        DB::table('siakad_mahasiswa')->where('status', 'lulus')->update(['status' => 'L']);

        Schema::table('siakad_mahasiswa', function (Blueprint $table): void {
            $table->enum('status', self::PDDIKTI_CODES)->default('A')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('siakad_mahasiswa') || ! Schema::hasColumn('siakad_mahasiswa', 'status')) {
            return;
        }

        Schema::table('siakad_mahasiswa', function (Blueprint $table): void {
            $table->enum('status', self::TRANSITION_CODES)->default('A')->change();
        });

        DB::table('siakad_mahasiswa')->where('status', 'A')->update(['status' => 'aktif']);
        DB::table('siakad_mahasiswa')->where('status', 'C')->update(['status' => 'cuti']);
        DB::table('siakad_mahasiswa')->whereIn('status', ['G', 'M', 'U'])->update(['status' => 'aktif']);
        DB::table('siakad_mahasiswa')->where('status', 'N')->update(['status' => 'dropout']);
        DB::table('siakad_mahasiswa')->where('status', 'L')->update(['status' => 'lulus']);

        Schema::table('siakad_mahasiswa', function (Blueprint $table): void {
            $table->enum('status', self::LEGACY_CODES)->default('aktif')->change();
        });
    }
};
