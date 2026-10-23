<?php

namespace App\Models\Siakad;

use App\Models\Siakad\Dosen;
use App\Models\Sinapra\Ruangan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TugasAkhirSidang extends Model
{
    use HasFactory;

    protected $table = 'siakad_ta_sidang';

    public const JENIS_PROPOSAL = 'proposal';
    public const JENIS_SEMINAR_HASIL = 'seminar_hasil';
    public const JENIS_SIDANG_SKRIPSI = 'sidang_skripsi';

    public const STATUS_BELUM_SIDANG = 'belum_sidang';
    public const STATUS_LULUS_TANPA_REVISI = 'lulus_tanpa_revisi';
    public const STATUS_LULUS_REVISI_RINGAN = 'lulus_revisi_ringan';
    public const STATUS_LULUS_REVISI_BERAT = 'lulus_revisi_berat';
    public const STATUS_MENGULANG = 'mengulang';

    protected $fillable = [
        'tugas_akhir_id',
        'jenis_sidang',
        'periode_sidang',
        'tanggal_sidang',
        'jam_mulai',
        'jam_selesai',
        'ruangan_id',
        'ruangan_nama_manual',
        'ketua_sidang_id',
        'sekretaris_sidang_id',
        'status_kelulusan',
        'batas_waktu_revisi',
        'catatan_sidang',
        'berkas_bap',
        'is_locked',
    ];

    protected $casts = [
        'tanggal_sidang' => 'date',
        'batas_waktu_revisi' => 'date',
        'is_locked' => 'boolean',
    ];

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class, 'tugas_akhir_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function ketuaSidang(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'ketua_sidang_id');
    }

    public function sekretarisSidang(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'sekretaris_sidang_id');
    }

    public function pengujis(): HasMany
    {
        return $this->hasMany(TugasAkhirPenguji::class, 'sidang_id');
    }
}
