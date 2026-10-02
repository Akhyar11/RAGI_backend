<?php

namespace App\Support;

/**
 * Katalog definisi hierarki menu (source of truth) yang dipakai bersama oleh
 * MenuSeeder, perbaikan relasi menu↔permission, dan migrasi sinkronisasi menu.
 *
 * Definisi disimpan di `database/seeders/IAM/menu_definitions.php` sebagai
 * array literal (tanpa logika) agar tidak lagi di-parsing via regex/eval.
 */
class MenuCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return require database_path('seeders/IAM/menu_definitions.php');
    }
}
