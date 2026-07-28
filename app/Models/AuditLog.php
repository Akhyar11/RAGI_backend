<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'module', 'action', 'table_name', 'record_id', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    const UPDATED_AT = null;
    
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
