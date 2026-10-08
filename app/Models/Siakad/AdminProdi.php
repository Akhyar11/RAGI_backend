<?php

namespace App\Models\Siakad;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminProdi extends Model
{
    use HasFactory;

    protected $table = 'siakad_admin_prodi';

    protected $fillable = [
        'program_studi_id',
        'user_id',
        'jabatan',
        'can_approve_rps',
        'is_active',
        'assigned_by',
    ];

    protected $casts = [
        'can_approve_rps' => 'boolean',
        'is_active' => 'boolean',
        'program_studi_id' => 'integer',
        'user_id' => 'integer',
        'assigned_by' => 'integer',
    ];

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function menus()
    {
        return $this->belongsToMany(\App\Models\Menu::class, 'siakad_admin_prodi_menus', 'admin_prodi_id', 'menu_id')
            ->withTimestamps();
    }
}
