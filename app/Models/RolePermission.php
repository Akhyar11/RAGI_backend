<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['role_id', 'permission_id'])]
class RolePermission extends Model
{
    const UPDATED_AT = null;
}
