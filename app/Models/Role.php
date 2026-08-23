<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['name', 'slug', 'description', 'is_active'])]
class Role extends Model
{
    use HasFactory;

    protected $table = 'core_roles';

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'core_role_permissions', 'role_id', 'permission_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'core_user_roles', 'role_id', 'user_id');
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'core_menu_role');
    }
}
