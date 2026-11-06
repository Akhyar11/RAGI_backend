<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\KuesionerSpmb;
use App\Models\Spmb\PengumumanSpmb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GelombangPenerimaan extends Model
{
    use HasFactory;

    protected $table = 'spmb_gelombang_penerimaan';

    protected $fillable = [
        'jalur_masuk_id',
        'nama',
        'tanggal_buka',
        'tanggal_tutup',
        'tanggal_pengumuman',
        'kuota_total',
        'kuota_terisi',
        'biaya_pendaftaran',
        'potongan_biaya_daftar_ulang',
        'status',
    ];

    protected $casts = [
        'tanggal_pengumuman' => 'date',
        'biaya_pendaftaran' => 'decimal:2',
        'potongan_biaya_daftar_ulang' => 'decimal:2',
    ];

    protected $appends = ['pendaftaran_dibuka'];

    /**
     * API exposes recurring periods as MM-DD. The old required DATE columns
     * remain as storage anchors; the cycle columns are the source of truth.
     */
    public function getTanggalBukaAttribute($value): ?string
    {
        return $this->attributes['tanggal_buka_siklus']
            ?? ($value ? Carbon::parse($value)->format('m-d') : null);
    }

    public function setTanggalBukaAttribute($value): void
    {
        $this->attributes['tanggal_buka_siklus'] = $this->normalizePeriodDate($value);
        $this->syncLegacyDateColumns();
    }

    public function getTanggalTutupAttribute($value): ?string
    {
        return $this->attributes['tanggal_tutup_siklus']
            ?? ($value ? Carbon::parse($value)->format('m-d') : null);
    }

    public function setTanggalTutupAttribute($value): void
    {
        $this->attributes['tanggal_tutup_siklus'] = $this->normalizePeriodDate($value);
        $this->syncLegacyDateColumns();
    }

    public function getPendaftaranDibukaAttribute(): bool
    {
        return $this->isPendaftaranDibuka();
    }

    public function isPendaftaranDibuka(?Carbon $now = null): bool
    {
        if ($this->status !== 'aktif' || ! $this->tanggal_buka || ! $this->tanggal_tutup) {
            return false;
        }

        $today = ($now ?? now(config('spmb.timezone', 'Asia/Jakarta')))
            ->timezone(config('spmb.timezone', 'Asia/Jakarta'))
            ->format('m-d');
        $start = $this->tanggal_buka;
        $end = $this->tanggal_tutup;

        return $start <= $end
            ? $today >= $start && $today <= $end
            : $today >= $start || $today <= $end;
    }

    public function scopePendaftaranDibuka(Builder $query, ?Carbon $now = null): Builder
    {
        $today = ($now ?? now(config('spmb.timezone', 'Asia/Jakarta')))
            ->timezone(config('spmb.timezone', 'Asia/Jakarta'))
            ->format('m-d');

        return $query->where('status', 'aktif')->where(function (Builder $q) use ($today) {
            $q->where(function (Builder $normal) use ($today) {
                $normal->whereColumn('tanggal_buka_siklus', '<=', 'tanggal_tutup_siklus')
                    ->where('tanggal_buka_siklus', '<=', $today)
                    ->where('tanggal_tutup_siklus', '>=', $today);
            })->orWhere(function (Builder $wrap) use ($today) {
                $wrap->whereColumn('tanggal_buka_siklus', '>', 'tanggal_tutup_siklus')
                    ->where(function (Builder $window) use ($today) {
                        $window->where('tanggal_buka_siklus', '<=', $today)
                            ->orWhere('tanggal_tutup_siklus', '>=', $today);
                    });
            });
        });
    }

    private function normalizePeriodDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        if (preg_match('/^\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return Carbon::parse($value)->format('m-d');
    }

    private function syncLegacyDateColumns(): void
    {
        $start = $this->attributes['tanggal_buka_siklus'] ?? null;
        $end = $this->attributes['tanggal_tutup_siklus'] ?? null;

        if ($start) {
            $this->attributes['tanggal_buka'] = '2000-'.$start;
        }
        if ($end) {
            // Both anchor years are leap years so a recurring February 29 remains valid.
            $year = $start && $end < $start ? 2004 : 2000;
            $this->attributes['tanggal_tutup'] = $year.'-'.$end;
        }
    }

    public function jalurMasuk()
    {
        return $this->belongsTo(JalurMasuk::class, 'jalur_masuk_id');
    }

    /** @deprecated Recurring periods do not encode a registration year. Use the candidate's created_at. */
    public function tahunPendaftaran(): ?int
    {
        return null;
    }

    public function pendaftaranCalonMhs()
    {
        return $this->hasMany(PendaftaranCalonMhs::class, 'gelombang_id');
    }

    public function kuesionerSpmb()
    {
        return $this->hasMany(KuesionerSpmb::class, 'gelombang_id');
    }

    public function pengumumanSpmb()
    {
        return $this->hasMany(PengumumanSpmb::class, 'gelombang_id');
    }
}
