<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spmb_master_sekolah')) {
            return;
        }

        Schema::create('spmb_master_sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255);
            $table->string('nama_normal', 255)->unique();
            $table->text('alamat')->nullable();
            $table->string('jenis', 50)->nullable();
            $table->string('sumber', 20)->default('manual');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_master_sekolah');
    }
};
