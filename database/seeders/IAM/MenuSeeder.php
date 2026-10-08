<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('core_menu_role')->truncate();
        Menu::truncate();
        Schema::enableForeignKeyConstraints();

$menus = \App\Support\MenuCatalog::definitions();

        foreach ($menus as $menuData) {
            $permissionId = null;
            if (isset($menuData['permission_slug'])) {
                $permission = Permission::where('slug', $menuData['permission_slug'])->first();
                $permissionId = $permission ? $permission->id : null;
            }

            $parent = Menu::create([
                'name' => $menuData['name'],
                'url' => $menuData['url'],
                'icon' => $menuData['icon'],
                'module' => $menuData['module'],
                'permission_id' => $permissionId,
                'order_index' => $menuData['order_index'],
                'is_active' => true,
            ]);

            if (isset($menuData['children'])) {
                foreach ($menuData['children'] as $childData) {
                    $childPermissionId = null;
                    if (isset($childData['permission_slug'])) {
                        $childPermission = Permission::where('slug', $childData['permission_slug'])->first();
                        $childPermissionId = $childPermission ? $childPermission->id : null;
                    }

                    Menu::create([
                        'parent_id' => $parent->id,
                        'name' => $childData['name'],
                        'url' => $childData['url'],
                        'icon' => $childData['icon'],
                        'module' => $childData['module'],
                        'permission_id' => $childPermissionId,
                        'order_index' => $childData['order_index'],
                        'is_active' => true,
                    ]);
                }
            }
        }

        // Attach default role_menus
        $allMenuIds = Menu::pluck('id')->toArray();
        $akunKeamananIds = Menu::where('url', '#akun_keamanan')
            ->orWhere('url', 'like', '/profile%')
            ->pluck('id')
            ->toArray();

        $roles = \App\Models\Role::all();
        foreach ($roles as $role) {
            if (in_array($role->slug, ['superadmin', 'admin'])) {
                $role->menus()->sync($allMenuIds);
            } else {
                // Ensure profile/security menus are attached for all roles
                $role->menus()->syncWithoutDetaching($akunKeamananIds);

                // Sync module specific menus
                $moduleSlug = str_replace('admin_', '', $role->slug);
                $moduleSlug = str_replace('operator_', '', $moduleSlug);
                if (in_array($role->slug, ['operator_sikeu', 'kabag_keuangan'])) {
                    $moduleSlug = 'sikeu';
                }
                $roleMenuIds = Menu::where(function ($mq) use ($moduleSlug) {
                        $mq->where('module', $moduleSlug)
                           ->whereNull('permission_id')
                           ->where('url', 'not like', '%/master/%')
                           ->where('url', 'not like', '#master_%');
                    })
                    ->orWhere(function ($ssoQ) {
                        $ssoQ->where('module', 'sso')
                             ->where(function ($sq) {
                                 $sq->where('url', 'like', '/profile%')
                                    ->orWhere('url', '#akun_keamanan')
                                    ->orWhere('url', '/dashboard');
                             });
                    })
                    ->pluck('id')
                    ->toArray();
                if (!empty($roleMenuIds)) {
                    $role->menus()->syncWithoutDetaching($roleMenuIds);
                }

                if (in_array($role->slug, ['operator_sikeu', 'kabag_keuangan'])) {
                    $sikeuMenuIds = Menu::where('module', 'sikeu')->pluck('id')->toArray();
                    $role->menus()->syncWithoutDetaching($sikeuMenuIds);
                }
            }
        }

        // Attach all SIMPEG menus to admin_simpeg and operator_sdm
        $simpegMenuIds = Menu::where('module', 'simpeg')->pluck('id')->toArray();
        $adminSimpegRole = \App\Models\Role::where('slug', 'admin_simpeg')->first();
        if ($adminSimpegRole) {
            $adminSimpegRole->menus()->syncWithoutDetaching($simpegMenuIds);
        }
        $operatorSdmRole = \App\Models\Role::where('slug', 'operator_sdm')->first();
        if ($operatorSdmRole) {
            $operatorSdmRole->menus()->syncWithoutDetaching($simpegMenuIds);
        }

        // Attach all SINAPRA menus to admin_sarpras and admin_laboratorium
        $sinapraMenuIds = Menu::where('module', 'sinapra')->pluck('id')->toArray();
        $adminSarprasRole = \App\Models\Role::where('slug', 'admin_sarpras')->first();
        if ($adminSarprasRole) {
            $adminSarprasRole->menus()->syncWithoutDetaching($sinapraMenuIds);
        }
        $adminLabRole = \App\Models\Role::where('slug', 'admin_laboratorium')->first();
        if ($adminLabRole) {
            $adminLabRole->menus()->syncWithoutDetaching($sinapraMenuIds);
        }

        $dosenRole = \App\Models\Role::where('slug', 'dosen')->first();
        if ($dosenRole) {
            $dosenSimpegMenuIds = Menu::where('module', 'simpeg')
                ->whereIn('url', [
                    '/simpeg',
                    '#layanan_simpeg',
                    '#kepegawaian_simpeg',
                    '/simpeg/presensi',
                    '/simpeg/cuti',
                    '/simpeg/payroll',
                    '/simpeg/kinerja',
                    '/simpeg/usulan-jafung',
                    '/simpeg/kompetensi',
                    '/simpeg/surat-tugas',
                    '/simpeg/sk-pegawai',
                    '/simpeg/dokumen',
                ])
                ->pluck('id')
                ->toArray();
            $dosenRole->menus()->syncWithoutDetaching($dosenSimpegMenuIds);
        }

        $tendikRole = \App\Models\Role::where('slug', 'tendik')->first();
        if ($tendikRole) {
            $tendikSimpegMenuIds = Menu::where('module', 'simpeg')
                ->whereIn('url', [
                    '/simpeg',
                    '#layanan_simpeg',
                    '#kepegawaian_simpeg',
                    '/simpeg/presensi',
                    '/simpeg/cuti',
                    '/simpeg/payroll',
                    '/simpeg/kinerja',
                    '/simpeg/kompetensi',
                    '/simpeg/surat-tugas',
                    '/simpeg/sk-pegawai',
                    '/simpeg/dokumen',
                ])
                ->pluck('id')
                ->toArray();
            $tendikRole->menus()->syncWithoutDetaching($tendikSimpegMenuIds);
        }

        // Dosen & Admin OBE Homebase Prodi: portal SIAKAD
        if ($dosenRole) {
            $dosenSiakadMenuIds = Menu::where('module', 'siakad')
                ->whereIn('url', [
                    '/siakad',
                    '#perkuliahan_siakad',
                    '/siakad/nilai',
                    '/siakad/bimbingan',
                    '#obe_siakad',
                    '/siakad/obe/tahun-kurikulum',
                    '/siakad/obe/rumpun-mk',
                    '/siakad/obe/jenis-cpl',
                    '/siakad/obe/matakuliah',
                    '/siakad/obe/distribusi-mk',
                    '/siakad/obe/rubrik',
                    '/siakad/obe/cpmk',
                    '#bahan_kajian_siakad',
                    '/siakad/obe/bahan-kajian',
                    '/siakad/obe/pemetaan-cpl-bk',
                    '/siakad/obe/pemetaan-bk-mk',
                    '#skl_siakad',
                    '/siakad/obe/profil-lulusan',
                    '/siakad/obe/profesi',
                    '/siakad/obe/cpl',
                    '/siakad/obe/pemetaan-cpl-pl',
                    '/siakad/hasil-studi',
                    '/siakad/civitas/mahasiswa',
                    '/siakad/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $dosenRole->menus()->syncWithoutDetaching($dosenSiakadMenuIds);
        }

        // Portal mandiri mahasiswa: dashboard + tagihan via baseAllowed,
        // menu DB untuk dispensasi & panduan agar lolos CheckMenuAccess.
        $mahasiswaRole = \App\Models\Role::where('slug', 'mahasiswa')->first();
        if ($mahasiswaRole) {
            $mhsSikeuMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', ['/sikeu', '/sikeu/dispensasi', '/sikeu/panduan'])
                ->pluck('id')
                ->toArray();
            $mahasiswaRole->menus()->syncWithoutDetaching($mhsSikeuMenuIds);
            // Portal SIAKAD mahasiswa: dashboard, KRS, jadwal, hasil studi, panduan
            $mhsSiakadMenuIds = Menu::where('module', 'siakad')
                ->whereIn('url', [
                    '/siakad',
                    '#perkuliahan_siakad',
                    '/siakad/perkuliahan/kelas',
                    '/siakad/krs',
                    '/siakad/hasil-studi',
                    '/siakad/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $mahasiswaRole->menus()->syncWithoutDetaching($mhsSiakadMenuIds);
        }

        // LMS standalone: dosen + mahasiswa wajib punya menu /lms
        $lmsMenuIds = Menu::where('module', 'lms')->pluck('id')->toArray();
        if (!empty($lmsMenuIds)) {
            if (isset($dosenRole) && $dosenRole) {
                $dosenRole->menus()->syncWithoutDetaching($lmsMenuIds);
            }
            if (isset($mahasiswaRole) && $mahasiswaRole) {
                $mahasiswaRole->menus()->syncWithoutDetaching($lmsMenuIds);
            }
        }

        // Pimpinan: dashboard + approval direktur + laporan & pantauan (read-only eksekutif).
        // Tahap sarpras/keuangan disembunyikan; akuntansi hanya laporan; plus piutang.
        $pimpinanRole = \App\Models\Role::where('slug', 'pimpinan')->first();
        if ($pimpinanRole) {
            $pimpinanSikeuMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', [
                    '/sikeu',
                    '#pengeluaran_sikeu',
                    '/sikeu/approval/direktur',
                    '#akuntansi_sikeu',
                    '/sikeu/akuntansi/laporan',
                    '/sikeu/dispensasi',
                    '/sikeu/pengajuan',
                    '/sikeu/piutang',
                    '/sikeu/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $pimpinanRole->menus()->syncWithoutDetaching($pimpinanSikeuMenuIds);
        }

        // Admin Keuangan Akuntansi: jurnal, buku besar, COA, laporan, kas & pajak.
        $adminKeuAkuntansiRole = \App\Models\Role::where('slug', 'admin_keuangan_akuntansi')->first();
        if ($adminKeuAkuntansiRole) {
            $akuntansiMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', [
                    '/sikeu',
                    '#pengeluaran_sikeu',
                    '/sikeu/kas-kecil',
                    '#akuntansi_sikeu',
                    '/sikeu/akuntansi/jurnal',
                    '/sikeu/akuntansi/buku-besar',
                    '/sikeu/akuntansi/coa',
                    '/sikeu/akuntansi/laporan',
                    '/sikeu/unit-kas',
                    '/sikeu/pengeluaran',
                    '/sikeu/pemasukan',
                    '/sikeu/pajak',
                    '/sikeu/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $adminKeuAkuntansiRole->menus()->syncWithoutDetaching($akuntansiMenuIds);
        }

        // Petugas Kas Kecil: menu kas kecil + parent groupnya, tanpa menu keuangan lain.
        $petugasKasKecilRole = \App\Models\Role::where('slug', 'petugas_kas_kecil')->first();
        if ($petugasKasKecilRole) {
            $petugasKasKecilMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', [
                    '/sikeu',
                    '#pengeluaran_sikeu',
                    '/sikeu/kas-kecil',
                ])
                ->pluck('id')
                ->toArray();
            $petugasKasKecilRole->menus()->syncWithoutDetaching($petugasKasKecilMenuIds);
        }

        // Admin Keuangan Pembayaran Mahasiswa: tagihan, kasir, validasi, gateway.
        $adminKeuPembayaranRole = \App\Models\Role::where('slug', 'admin_keuangan_pembayaran')->first();
        if ($adminKeuPembayaranRole) {
            $pembayaranMenuIds = Menu::where('module', 'sikeu')
                ->whereIn('url', [
                    '/sikeu',
                    '#pembayaran_mhs_sikeu',
                    '/sikeu/pembayaran-mahasiswa/tarif',
                    '/sikeu/pembayaran-mahasiswa/tagihan',
                    '/sikeu/pembayaran-mahasiswa/bayar',
                    '/sikeu/pembayaran-mahasiswa/potongan',
                    '/sikeu/piutang',
                    '/sikeu/dispensasi',
                    '/sikeu/pembayaran-mahasiswa/validasi-manual',
                    '/sikeu/dispensasi',
                    '/sikeu/unit-kas',
                    '/sikeu/payment-gateway',
                    '/sikeu/panduan',
                ])
                ->pluck('id')
                ->toArray();
            $adminKeuPembayaranRole->menus()->syncWithoutDetaching($pembayaranMenuIds);
        }

        // Attach SPMB menus to SPMB admin role (slug resmi: admin_spmb)
        $spmbMenuIds = Menu::where('module', 'spmb')->pluck('id')->toArray();
        $adminSpmbRole = \App\Models\Role::where('slug', 'admin_spmb')->first();
        if ($adminSpmbRole) {
            $adminSpmbRole->menus()->syncWithoutDetaching($spmbMenuIds);
        }

        // Attach ARSIP menus to Arsip admin role (slug resmi: admin_arsip)
        $arsipMenuIds = Menu::where('module', 'arsip')->pluck('id')->toArray();
        $adminArsipRole = \App\Models\Role::where('slug', 'admin_arsip')->first();
        if ($adminArsipRole) {
            $adminArsipRole->menus()->syncWithoutDetaching($arsipMenuIds);
        }

        // Attach SIAKAD menus to SIAKAD admin role (slug resmi: admin_siakad)
        // Admin SIAKAD / BAAK difokuskan pada manajemen operasional BAAK & Master Akademik.
        // Menu operasional dosen murni / OBE detail prodi (KHS/Transkrip, Penilaian Kelas OBE, CPMK, RPS, Bank Soal, Ketertiban Nilai Dosen) tidak perlu masuk ke Admin SIAKAD.
        $adminSiakadMenuIds = Menu::where('module', 'siakad')
            ->whereIn('url', [
                '/siakad',
                '#perkuliahan_siakad',
                '/siakad/perkuliahan/kelas',
                '/siakad/krs',
                '/siakad/obe', // Pemantauan OBE Global Institusi
                '#civitas_siakad',
                '/siakad/civitas/mahasiswa',
                '/siakad/civitas/konversi',
                '/siakad/civitas/dosen',
                '/siakad/civitas/biodata',
                '/siakad/civitas/beasiswa',
                '#master_siakad',
                '/siakad/master/tahun-akademik',
                '/siakad/master/fakultas',
                '/siakad/master/kurikulum',
                '/siakad/master/matakuliah',
                '/siakad/master/skala-nilai',
                '/siakad/master/konfigurasi-penilaian',
                '/siakad/master/referensi',
                '/siakad/master/tipe-referensi',
                '#feeder_siakad',
                '/siakad/feeder-sync',
                '/siakad/panduan',
            ])
            ->pluck('id')
            ->toArray();

        $adminSiakadRole = \App\Models\Role::where('slug', 'admin_siakad')->first();
        if ($adminSiakadRole) {
            $adminSiakadRole->menus()->sync($adminSiakadMenuIds);
        }
    }
}
