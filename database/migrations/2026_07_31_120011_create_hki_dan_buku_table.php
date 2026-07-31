<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hki_dan_buku', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->nullable()->constrained('proposal_kegiatan')->nullOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->enum('jenis_luaran', [
                'paten', 'hak_cipta', 'desain_industri', 
                'rahasia_dagang', 'buku_ajar', 'buku_monograf', 'book_chapter'
            ]);
            $table->text('judul');
            $table->string('nomor_pencatatan_isbn', 100)->unique();
            $table->string('penerbit_lembaga', 255);
            $table->date('tgl_terbit_catat');
            $table->string('file_sertifikat_buku', 255)->nullable();
            $table->boolean('is_verified_lppm')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hki_dan_buku');
    }
};
