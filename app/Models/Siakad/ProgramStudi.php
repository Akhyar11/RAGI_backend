<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Siakad\Fakultas;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Dosen;

class ProgramStudi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_program_studi';

    protected $fillable = [
        'fakultas_id',
        'kaprodi_id',
        'kode_prodi',
        'prefix_nim',
        'kode_prodi_dikti',
        'id_feeder',
        'nama',
        'jenjang',
        'akreditasi',
        'akreditasi_berlaku_sampai',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'akreditasi_berlaku_sampai' => 'date',
    ];

    public function fakultas()
    {
        return $this->belongsTo(Fakultas::class, 'fakultas_id');
    }

    public function kaprodi()
    {
        return $this->belongsTo(Dosen::class, 'kaprodi_id');
    }

    public function kurikulums()
    {
        return $this->hasMany(Kurikulum::class, 'program_studi_id');
    }

    public function mahasiswas()
    {
        return $this->hasMany(Mahasiswa::class, 'program_studi_id');
    }

    public function dosens()
    {
        return $this->hasMany(Dosen::class, 'program_studi_id');
    }

    public function cpls()
    {
        return $this->hasMany(\App\Models\Siakad\Cpl::class, 'program_studi_id');
    }

    /**
     * Relasi ke Ruangan SINAPRA
     */
    public function ruangans()
    {
        return $this->hasMany(\App\Models\Ruangan::class, 'program_studi_id');
    }

    /**
     * Relasi ke Inventaris Aset SINAPRA
     */
    public function asets()
    {
        return $this->hasMany(\App\Models\Aset::class, 'program_studi_id');
    }

    /**
     * Relasi ke Laboran yang ditugaskan di Prodi ini
     */
    public function laborans()
    {
        return $this->belongsToMany(\App\Models\User::class, 'sinapra_laboran_prodi', 'program_studi_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Relasi ke Role Laboran Pengampu SINAPRA
     */
    public function sinapraRoles()
    {
        return $this->belongsToMany(\App\Models\Role::class, 'sinapra_prodi_roles', 'program_studi_id', 'role_id')
            ->withPivot('keterangan')
            ->withTimestamps();
    }

    /**
     * Relasi ke Penugasan Admin OBE / Tim Kurikulum Prodi SIAKAD
     */
    public function adminProdis()
    {
        return $this->hasMany(\App\Models\Siakad\AdminProdi::class, 'program_studi_id');
    }

    public function obeAdmins()
    {
        return $this->belongsToMany(\App\Models\User::class, 'siakad_admin_prodi', 'program_studi_id', 'user_id')
            ->withPivot(['jabatan', 'can_approve_rps', 'is_active', 'assigned_by'])
            ->withTimestamps();
    }
}
