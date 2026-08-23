<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'role_id', 'assigned_by', 'valid_from', 'valid_until'])]
class UserRole extends Model
{
    protected $table = 'core_user_roles';
    const UPDATED_AT = null;
    
    protected function casts(): array
    {
        return [
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
        ];
    }
}
