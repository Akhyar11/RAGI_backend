<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'code', 'description', 'is_active', 'primary_color'])]
class Module extends Model
{
    use HasFactory;

    protected $table = 'core_modules';
}
