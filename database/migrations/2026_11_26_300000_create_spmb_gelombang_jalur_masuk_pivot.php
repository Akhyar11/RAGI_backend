<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu gelombang dapat dipetakan ke banyak jalur; tanpa baris = gelombang umum.
        Schema::create('spmb_gelombang_jalur_masuk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_id')->constrained('spmb_gelombang_penerimaan')->cascadeOnDelete();
            $table->foreignId('jalur_masuk_id')->constrained('spmb_jalur_masuk')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['gelombang_id', 'jalur_masuk_id'], 'spmb_gelombang_jalur_unique');
        });

        // Pindahkan pemetaan lama (satu jalur per gelombang) ke tabel penghubung.
        $now = now();
        $rows = DB::table('spmb_gelombang_penerimaan')
            ->whereNotNull('jalur_masuk_id')
            ->get(['id', 'jalur_masuk_id'])
            ->map(fn ($g) => [
                'gelombang_id' => $g->id,
                'jalur_masuk_id' => $g->jalur_masuk_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if ($rows !== []) {
            DB::table('spmb_gelombang_jalur_masuk')->insert($rows);
            DB::table('spmb_gelombang_penerimaan')->whereNotNull('jalur_masuk_id')->update(['jalur_masuk_id' => null]);
        }
    }

    public function down(): void
    {
        // Kembalikan jalur tunggal hanya untuk gelombang yang dipetakan ke tepat satu jalur.
        $single = DB::table('spmb_gelombang_jalur_masuk')
            ->select('gelombang_id', DB::raw('min(jalur_masuk_id) as jalur_masuk_id'))
            ->groupBy('gelombang_id')
            ->havingRaw('count(*) = 1')
            ->get();
        foreach ($single as $row) {
            DB::table('spmb_gelombang_penerimaan')->where('id', $row->gelombang_id)->update(['jalur_masuk_id' => $row->jalur_masuk_id]);
        }

        Schema::dropIfExists('spmb_gelombang_jalur_masuk');
    }
};
