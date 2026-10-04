<?php

namespace App\Models\Sinapra;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdiRole extends Model
{
    protected $table = 'sinapra_prodi_roles';

    protected $fillable = [
        'program_studi_id',
        'role_id',
        'keterangan',
    ];

    /**
     * Relasi ke Program Studi (SIAKAD)
     */
    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siakad\ProgramStudi::class, 'program_studi_id');
    }

    /**
     * Relasi ke Core Role (RBAC)
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Role::class, 'role_id');
    }
}
