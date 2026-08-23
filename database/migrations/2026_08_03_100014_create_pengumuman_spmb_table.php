<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_pengumuman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_id')->constrained('spmb_gelombang_penerimaan')->cascadeOnDelete();
            $table->string('judul');
            $table->text('isi');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_pengumuman');
    }
};
