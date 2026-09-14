<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'core_system_settings';

    protected $fillable = ['key', 'value', 'description'];

    /**
     * Mendapatkan nilai pengaturan berdasarkan key.
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Menyimpan atau memperbarui nilai pengaturan berdasarkan key.
     */
    public static function set(string $key, $value, ?string $description = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'description' => $description,
            ]
        );
    }
}
