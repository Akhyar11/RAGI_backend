<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use App\Models\Role;

/**
 * Permission granular data bimbingan PA (mengganti hardcode role & Gate ad-hoc).
 *
 * - `siakad.pa.read`   : melihat catatan, laporan, aktivitas, komposisi & cetak PA.
 * - `siakad.pa.create` : menambah catatan, pengajuan konsultasi, laporan & PKL/insiden.
 * - `siakad.pa.update` : mengubah catatan, memproses booking, verifikasi/eskalasi.
 * - `siakad.pa.delete` : menghapus catatan, jadwal & PKL.
 *
 * Sebelumnya Dosen PA (role `dosen`) tidak memiliki `siakad.krs.approve`,
 * `siakad.mahasiswa.manage`, maupun `siakad.krs.create` sehingga GATE
 * `siakad.pa.catatan.create` / `siakad.krs.approve` menolak mereka menulis
 * catatan bimbingan (bug kritis). Keempat permission ini menambal celah tersebut.
 *
 * Role yang dipetakan hanya role resmi yang terdaftar di `RoleSeeder.php`:
 * `dosen`, `mahasiswa`, `admin_siakad`. `admin` & `superadmin` mendapat seluruh
 * permission otomatis via PermissionSeeder (auto-all). Kaprodi & wakil prodi
 * TIDAK di-hardcode di sini — kewenangan struktural mereka diperiksa secara
 * dinamis (penugasan prodi / Gate::before), bukan lewat slug role phantom.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'slug' => 'siakad.pa.read',
            'name' => 'Lihat Data Bimbingan PA',
            'action' => 'read',
            'description' => 'Melihat catatan, laporan, aktivitas & komposisi bimbingan PA',
        ],
        [
            'slug' => 'siakad.pa.create',
            'name' => 'Tambah Data Bimbingan PA',
            'action' => 'create',
            'description' => 'Menambah catatan, laporan, aktivitas, pengajuan konsultasi & data PKL/insiden PA',
        ],
        [
            'slug' => 'siakad.pa.update',
            'name' => 'Ubah Data Bimbingan PA',
            'action' => 'update',
            'description' => 'Mengubah catatan, laporan, jadwal, proses konsultasi & tindak lanjut insiden PA',
        ],
        [
            'slug' => 'siakad.pa.delete',
            'name' => 'Hapus Data Bimbingan PA',
            'action' => 'delete',
            'description' => 'Menghapus catatan, jadwal & data PKL bimbingan PA',
        ],
    ];

    // Role resmi (selaras RoleSeeder). admin/superadmin otomatis via auto-all.
    private const READ_ROLES = ['dosen', 'mahasiswa', 'admin_siakad'];
    private const CREATE_ROLES = ['dosen', 'mahasiswa', 'admin_siakad'];
    private const UPDATE_ROLES = ['dosen', 'admin_siakad'];
    private const DELETE_ROLES = ['dosen', 'admin_siakad'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $def) {
            Permission::updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'module' => 'siakad',
                    'action' => $def['action'],
                    'description' => $def['description'],
                ]
            );
        }

        $this->attach('siakad.pa.read', self::READ_ROLES);
        $this->attach('siakad.pa.create', self::CREATE_ROLES);
        $this->attach('siakad.pa.update', self::UPDATE_ROLES);
        $this->attach('siakad.pa.delete', self::DELETE_ROLES);
    }

    public function down(): void
    {
        $this->detach('siakad.pa.read', self::READ_ROLES);
        $this->detach('siakad.pa.create', self::CREATE_ROLES);
        $this->detach('siakad.pa.update', self::UPDATE_ROLES);
        $this->detach('siakad.pa.delete', self::DELETE_ROLES);

        Permission::whereIn('slug', [
            'siakad.pa.read',
            'siakad.pa.create',
            'siakad.pa.update',
            'siakad.pa.delete',
        ])->delete();
    }

    private function attach(string $slug, array $roleSlugs): void
    {
        $permission = Permission::where('slug', $slug)->first();
        if (! $permission) {
            return;
        }

        Role::whereIn('slug', $roleSlugs)
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }

    private function detach(string $slug, array $roleSlugs): void
    {
        $permission = Permission::where('slug', $slug)->first();
        if (! $permission) {
            return;
        }

        Role::whereIn('slug', $roleSlugs)
            ->get()
            ->each(fn (Role $role) => $role->permissions()->detach([$permission->id]));
    }
};
