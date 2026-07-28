<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'slug', 'module', 'action', 'description'])]
class Permission extends Model
{
    const UPDATED_AT = null;
}
