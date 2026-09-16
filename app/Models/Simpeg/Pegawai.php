<?php

namespace App\Models\Simpeg;

use App\Models\Attendance;
use App\Models\OfficeLocation;
use App\Models\ShiftTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pegawai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_pegawai';

    protected $fillable = [
        'user_id',
        'unit_kerja_id',
        'office_location_id',
        'shift_template_id',
        'nip',
        'nidn',
        'nuptk',
        'nik',
        'nama_lengkap',
        'gelar_depan',
        'gelar_belakang',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'jenis_pegawai',
        'status_kepegawaian',
        'tanggal_masuk',
        'tanggal_keluar',
        'status',
        'alamat',
        'telepon',
        'face_embedding',
        'face_enrolled_at',
        'consent_pdp_at',
        'is_active',
        'sinta_id',
        'scopus_id',
        'google_scholar_id',
        'orcid_id',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date:Y-m-d',
        'tanggal_masuk' => 'date:Y-m-d',
        'tanggal_keluar' => 'date:Y-m-d',
        'face_enrolled_at' => 'datetime',
        'consent_pdp_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Accessor aman untuk face_embedding (mendukung plain JSON legacy & ciphertext terenkripsi).
     */
    public function getFaceEmbeddingAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * Mutator enkripsi biometrik UU PDP.
     */
    public function setFaceEmbeddingAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['face_embedding'] = null;
        } else {
            $str = is_array($value) ? json_encode($value) : (string) $value;
            try {
                decrypt($str);
                $this->attributes['face_embedding'] = $str;
            } catch (\Throwable $e) {
                $this->attributes['face_embedding'] = encrypt($str);
            }
        }
    }

    // Accessor kompatibilitas dengan Employee mobile
    public function getEmployeeCodeAttribute(): ?string
    {
        return $this->nip;
    }

    public function getPositionAttribute(): ?string
    {
        return $this->jenis_pegawai ?: 'Staff';
    }

    public function getDepartmentAttribute(): ?string
    {
        return $this->unitKerja?->nama ?: 'General';
    }

    protected $appends = [
        'nama_gelar',
        'role_ids',
    ];

    public function getNamaGelarAttribute(): string
    {
        $depan = $this->gelar_depan ? "{$this->gelar_depan} " : '';
        $belakang = $this->gelar_belakang ? ", {$this->gelar_belakang}" : '';
        return "{$depan}{$this->nama_lengkap}{$belakang}";
    }

    public function getRoleIdsAttribute(): array
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->pluck('id')->toArray();
        }
        return $this->roles()->pluck('core_roles.id')->toArray();
    }

    public function roles()
    {
        return $this->belongsToMany(\App\Models\Role::class, 'simpeg_pegawai_roles', 'pegawai_id', 'role_id')->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function officeLocation()
    {
        return $this->belongsTo(OfficeLocation::class, 'office_location_id');
    }

    public function shiftTemplate()
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'pegawai_id');
    }

    public function presensiPegawai()
    {
        return $this->hasMany(PresensiPegawai::class, 'pegawai_id');
    }

    public function riwayatJabatan()
    {
        return $this->hasMany(RiwayatJabatan::class, 'pegawai_id');
    }

    public function riwayatPendidikan()
    {
        return $this->hasMany(RiwayatPendidikanPegawai::class, 'pegawai_id');
    }

    public function dosen()
    {
        return $this->hasOne(\App\Models\Siakad\Dosen::class, 'pegawai_id');
    }

    public function sertifikasiDosen()
    {
        return $this->hasMany(SertifikasiDosen::class, 'pegawai_id');
    }

    public function riwayatTes()
    {
        return $this->hasMany(RiwayatTes::class, 'pegawai_id');
    }

    public function riwayatPelatihan()
    {
        return $this->hasMany(RiwayatPelatihan::class, 'pegawai_id');
    }

    public function suratTugas()
    {
        return $this->hasMany(SuratTugas::class, 'pegawai_id');
    }

    public function penugasanDinas()
    {
        return $this->belongsToMany(SuratTugas::class, 'simpeg_surat_tugas_anggota', 'pegawai_id', 'surat_tugas_id')
            ->withPivot('peran')
            ->withTimestamps();
    }
}
