<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkalaNilai extends Model
{
    // Soft delete (kolom deleted_at): sync PDDIKTI memakai withTrashed() agar baris yang sudah dihapus tidak diduplikasi.
    use SoftDeletes;

    protected $table = 'siakad_skala_nilai';

    protected $fillable = [
        'program_studi_id',
        'id_feeder',
        'nilai_huruf',
        'bobot_indeks',
        'batas_bawah',
        'batas_atas',
        'is_lulus',
        'keterangan',
        'tanggal_mulai_efektif',
        'tanggal_akhir_efektif',
        'is_active',
    ];

    protected $casts = [
        'bobot_indeks' => 'decimal:2',
        'batas_bawah' => 'decimal:2',
        'batas_atas' => 'decimal:2',
        'is_lulus' => 'boolean',
        'is_active' => 'boolean',
        'tanggal_mulai_efektif' => 'date',
        'tanggal_akhir_efektif' => 'date',
    ];

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    /**
     * Skala aktif yang berlaku untuk satu prodi: skala khusus prodi bila ada, jika tidak skala standar (program_studi_id NULL).
     * Dipakai untuk menampilkan skala; konversi nilai tetap lewat konversiNilai().
     */
    public static function aktifUntukProdi(?int $prodiId)
    {
        if ($prodiId) {
            $khusus = static::where('is_active', true)->where('program_studi_id', $prodiId)->orderBy('bobot_indeks', 'desc')->get();
            if ($khusus->isNotEmpty()) {
                return $khusus;
            }
        }

        return static::where('is_active', true)->whereNull('program_studi_id')->orderBy('bobot_indeks', 'desc')->get();
    }

    /**
     * Cari nilai huruf & bobot indeks berdasarkan nilai angka (0 - 100).
     */
    public static function konversiNilai(float $nilaiAngka, ?int $prodiId = null): ?self
    {
        $query = static::where('is_active', true)
            ->where('batas_bawah', '<=', $nilaiAngka)
            ->where('batas_atas', '>=', $nilaiAngka);

        if ($prodiId) {
            $khusus = (clone $query)->where('program_studi_id', $prodiId)->orderBy('bobot_indeks', 'desc')->first();
            if ($khusus) {
                return $khusus;
            }
        }

        return $query->whereNull('program_studi_id')->orderBy('bobot_indeks', 'desc')->first();
    }
}
