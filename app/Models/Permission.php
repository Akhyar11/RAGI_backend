<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['name', 'slug', 'module', 'action', 'description'])]
class Permission extends Model
{
    use HasFactory;

    protected $table = 'core_permissions';

    const UPDATED_AT = null;
}
